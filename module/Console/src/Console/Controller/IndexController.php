<?php
namespace Console\Controller;

use Zend, Com;
use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController,
    Zend\Console\Request as ConsoleRequest;

class IndexController extends Com\Controller\AbstractController
{



    function runCronAction()
    {
        $request = $this->getRequest();
        
        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }
        
        $publicDir = PUBLIC_DIRECTORY;
        $coreDir = CORE_DIRECTORY;
        
        $sl = $this->getServiceLocator();
    
        $dbClient = $sl->get('App\Db\Client');
        
        $where = array();
        $where['deleted = ?'] = 0;
        $where['approved = ?'] = 1;
        $where['email_verified = ?'] = 1;
        
        $rowset = $dbClient->findby($where);
        
        $client = new Zend\Http\Client();
        $client->setMethod(Zend\Http\Request::METHOD_GET);
        
        // If we want a log this should be executed using wkhtmltopdf
        $bin = '/usr/local/bin/wkhtmltopdf';
        foreach($rowset as $row)
        {
            $url = "http://{$row->domain}/admin/cron.php";
            
            //
            #if(strpos($row->domain, 'paradisosolutions') === false)
                #continue;
            
            $command = "$bin $url {$coreDir}/data/log/{$row->domain}.pdf > {$coreDir}/data/log/{$row->domain}.log 2>&1 &";
            
            shell_exec($command);
        }
    }

    

    function createDatabasesAction()
    {
        $request = $this->getRequest();

        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }

        try
        {
            $sl = $this->getServiceLocator();
            
            $dbDatabase = $sl->get('App\Db\Database');
            $config = $sl->get('config');
            
            $console = Console::getInstance();
            
            $min = $config['freemium']['min_databases'];
            $existing = $dbDatabase->countFree();
            
            if($existing > $min)
            {
                $msg = "No need to create more databases, currenlty there are $existing databases";
                $console->writeLine($msg, 10);
                exit;
            }
            
            if($this->_isLocked(__method__))
            {
                $msg = "Already running...";
                $console->writeLine($msg, 10);
                exit;
            }
            else
            {
                $this->_lock(__method__);
                
                $msg = "Started at ".date('Y-m-d H:i:s') . PHP_EOL;
                $console->writeLine($msg, 11);
            }

            //
            $number = $config['freemium']['max_databases'];
            
            
            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');
            
            $topDomain = $config['freemium']['top_domain'];
            $mDataPath = $config['freemium']['path']['mdata'];
            #$mDataMasterPath = $config['freemium']['path']['mdata_master'];
            $masterSqlFile = $config['freemium']['path']['master_sql_file'];
            $configPath = $config['freemium']['path']['config'];
            
            $cpanelUser = $config['freemium']['cpanel']['username'];
            $cpanelPass = $config['freemium']['cpanel']['password'];
            
            $dbPrefix = $config['freemium']['db']['prefix'];
            $dbUser = $config['freemium']['db']['user'];
            $dbHost = $config['freemium']['db']['host'];
            $dbPassword = $config['freemium']['db']['password'];

            //
            $msg = "-----------------------------------";
            $console->writeLine($msg, 11);
            
            for($i=0; $i < $number; $i++)
            {
                /*************************************/
                // add the new database
                /*************************************/
                $data = array(
                    'db_host' => $dbHost
                    ,'db_name' => null
                    ,'db_user' => $dbUser
                    ,'db_password' => $dbPassword
                );
                $dbDatabase->doInsert($data);
                $databaseId = $dbDatabase->getLastInsertValue();
                
                $newDatabaseName = "client{$databaseId}";
                $newDatabaseNamePrefixed = "{$dbPrefix}client{$databaseId}";

                /*************************************/
                // update database name
                /*************************************/
                $data = array(
                    'db_name' => $newDatabaseNamePrefixed
                );
                
                $where = array(
                    'id' => $databaseId
                );
                $dbDatabase->doUpdate($data, $where);

                //
                $cp = $sl->get('cPanelApi');

                /*************************************/
                // create the database
                /*************************************/
                $response = $cp->api2_query($cpanelUser, 'MysqlFE', 'createdb', array(
                    'db' => $newDatabaseNamePrefixed,
                ));

                if(isset($response['error']) || isset($response['event']['error']))
                {
                    $this->_unlock(__method__);

                    $err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                    throw new \RuntimeException($err);
                }
                
                /*******************************/
                // update database schema
                /*******************************/
                $adapter = $sl->get('adapter');
                $sql = "ALTER SCHEMA `$newDatabaseNamePrefixed`  DEFAULT CHARACTER SET utf8  DEFAULT COLLATE utf8_general_ci \n";
                $statement = $adapter->query($sql, 'execute');
                $console->writeLine("Created database $newDatabaseNamePrefixed", 11);

                /*******************************/
                // Assign user to db
                /*******************************/
                $dbUserName = 'user';
                $response = $cp->api2_query($cpanelUser, 
                    'MysqlFE', 'setdbuserprivileges',
                    array(
                        'privileges' => 'ALL_PRIVILEGES',
                        'db' => $newDatabaseName,
                        'dbuser' => $dbUserName,
                    )
                );
                
                if(isset($response['error']) || isset($response['event']['error']))
                {
                    $this->_unlock(__method__);

                    $err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                    throw new \RuntimeException($err);
                }
                $console->writeLine("Assiged user to database $newDatabaseNamePrefixed", 11);

                /*******************************/
                // RESTORING database
                /*******************************/
                $console->writeLine("Restoring data into $newDatabaseNamePrefixed", 11);
                exec("mysql -u{$cpanelUser} -p{$cpanelPass} $newDatabaseNamePrefixed < $masterSqlFile");
                $console->writeLine("Restoration completed", 11);

                $msg = "-----------------------------------";
                $console->writeLine($msg, 11);
            }

            $this->_unlock(__method__);

            $msg = "\nEnded at ".date('Y-m-d H:i:s')."";
            $console->writeLine($msg, 11);
        }
        catch (RuntimeException $e)
        {
            $this->_unlock(__method__);
        }
    }
   
   
   
    function deleteAccountAction()
    {
        $request = $this->getRequest();

        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }
        
        try
        {
            $sl = $this->getServiceLocator();
            
            $config = $sl->get('config');
            
            $console = Console::getInstance();
            
            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');
            $dbClientDatabase = $sl->get('App\Db\Client\HasDatabase');
            
            $topDomain = $config['freemium']['top_domain'];
            $mDataPath = $config['freemium']['path']['mdata'];
            #$mDataMasterPath = $config['freemium']['path']['mdata_master'];
            $masterSqlFile = $config['freemium']['path']['master_sql_file'];
            $configPath = $config['freemium']['path']['config'];
            
            $cpanelUser = $config['freemium']['cpanel']['username'];
            $cpanelPass = $config['freemium']['cpanel']['password'];
            
            $dbPrefix = $config['freemium']['db']['prefix'];
            $dbUser = $config['freemium']['db']['user'];
            $dbHost = $config['freemium']['db']['host'];
            $dbPassword = $config['freemium']['db']['password'];

            //
            $msg = "-----------------------------------";
            $console->writeLine($msg, 11);
            
            $domain = $request->getParam('domain');
            
            $rowset = $dbClient->findByDomain($domain);
            $count = $rowset->count();
            if($count > 1)
            {
                $msg = "Ups, $count records found with the domain name $domain, please have a look first.";
                $console->writeLine($msg, 10);
                exit;
            }
            elseif(0 == $count)
            {
                $msg = "$count records found with the domain name $domain, please have a look first.";
                $console->writeLine($msg, 10);
                exit;
            }
            
            $rowClient = $rowset->current();
            $clientId = $rowClient->id;
            
            //
            $rowset = $dbDatabase->findDatabaseByClientId($clientId);
            $count = $rowset->count();
            if($count > 1)
            {
                $msg = "Ups, $count databases found relaed to the domain name $domain, please have a look first.";
                $console->writeLine($msg, 10);
                exit;
            }
            elseif(0 == $count)
            {
                $msg = "$count databases found related to the domain name $domain, please have a look first.";
                $console->writeLine($msg, 10);
                exit;
            }
            
            $rowDatabase = $rowset->current();
            $dbName = $rowDatabase->db_name;
            $dbNameNoPrefix = str_replace($dbPrefix, '', $dbName);
            
            $cp = $sl->get('cPanelApi');

            /*************************************/
            // delete the database
            /*************************************/
            $response = $cp->api2_query($cpanelUser, 'MysqlFE', 'deletedb', array(
                'db' => $dbName,
            ));
            
            if(isset($response['error']) || isset($response['event']['error']))
            {
                $err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                throw new \RuntimeException($err);
            }
            
            /*************************************/
            // delete the domain
            /*************************************/
            $response = $cp->unpark($cpanelUser, $domain);
             
            if(isset($response['error']) || isset($response['event']['error']))
            {
                $err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                throw new \RuntimeException($err);
            }
            
            
            /*************************************/
            // delete mdata folder
            /*************************************/
            "rm {$mDataPath}/$domain/ -Rf";
            exec("rm {$mDataPath}/$domain/ -Rf");
            
            
            /*************************************/
            // delete config file
            /*************************************/
            $configFilename = "{$configPath}/{$domain}.php";
            exec("rm $configFilename");
            
            
            // delete database from database database ;-)
            $where = array();
            $where['client_id = ?'] = $rowClient->id;
            $rowset = $dbClientDatabase->findBy($where);
            foreach($rowset as $row)
            {
                $where = array();
                $where['id = ?'] = $row->database_id;
                $dbDatabase->doDelete($where);
            }
            
            $where = array();
            $where['client_id = ?'] = $rowClient->id;
            $dbClientDatabase->doDelete($where);
            
            
            // update client email and domain 
            $where = array();
            $where['id = ?'] = $rowClient->id;
            
            $uid = uniqid();
            $data = array(
                'email' => "{$rowClient->email}.$uid"
                ,'domain' => "{$rowClient->domain}.$uid"
            );
            $dbClient->doUpdate($data, $where);
        }
        catch (RuntimeException $e)
        {
            ;
        }
    }
   
   
    
    protected function _isLocked($methodName)
    {
        $methodName = str_replace('\\', '.', $methodName);
        $methodName = str_replace(':', '-', $methodName);
        
        $fileName = "data/tmp/$methodName.lock";
        return file_exists($fileName);
    }
    
    
    
    protected function _lock($methodName)
    {
        $methodName = str_replace('\\', '.', $methodName);
        $methodName = str_replace(':', '-', $methodName);
        
        $fileName = "data/tmp/$methodName.lock";
        $handler = fopen($fileName, 'w') or die("can't open file");
        fclose($handler);
    }
    
    
    
    protected function _unlock($methodName)
    {
        $methodName = str_replace('\\', '.', $methodName);
        $methodName = str_replace(':', '-', $methodName);
        
        $fileName = "data/tmp/$methodName.lock";
        unlink($fileName);
    }
}