<?php
namespace Console\Controller;

use Zend, Com;
use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController,
    Zend\Console\Request as ConsoleRequest;

class IndexController extends Com\Controller\AbstractController
{

    function createDatabasesAction()
    {
        $request = $this->getRequest();
    
        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest)
        {
            throw new \RuntimeException('You can only use this action from a console!');
        }

        
        $number = $request->getParam('number');
        
        try
        {
            $sl = $this->getServiceLocator();
            $config = $sl->get('config');
            
            $console = Console::getInstance();
            
            if(is_null($number))
            {
                $msg = "Need the number of databases to be created";
                $console->writeLine($msg, 10);
                exit;
            }

            $min = $config['freemium']['max_databases'];
            if((abs((int)$number) != $number) || ($number <= 0) || ($number > $min))
            {
                $msg = "<number> parameter has to be an integer value between 1 and $min";
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
            
            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');
            
            $config = $sl->get('config');
            
            $topDomain = $config['freemium']['top_domain'];
            $scriptsPath = $config['freemium']['path']['scripts'];
            $mDataPath = $config['freemium']['path']['mdata'];
            $mDataMasterPath = $config['freemium']['path']['mdata_master'];
            $masterSqlFile = $config['freemium']['path']['master_sql_file'];
            $configPath = $config['freemium']['path']['config'];
            
            $cpanelUser = $config['freemium']['cpanel']['username'];
            $cpanelPass = $config['freemium']['cpanel']['password'];
            
            $dbPrefix =  $config['freemium']['db']['prefix'];
            $dbUser =  $config['freemium']['db']['user'];
            $dbHost =  $config['freemium']['db']['host'];
            $dbPassword =  $config['freemium']['db']['password'];

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
                    ,'client_id' => null
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
                    'db' => $newDatabaseName,
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
                $grant_user = $cp->api2_query(CPANEL_USER, 
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
            ;
        }
   }
   
   
   protected function _isLocked($methodName)
   {
      $methodName = str_replace('\\', '.', $methodName);
      
      $fileName = "data/tmp/$methodName.lock";
      return file_exists($fileName);
   }
   
   protected function _lock($methodName)
   {
      $methodName = str_replace('\\', '.', $methodName);
      
      $fileName = "data/tmp/$methodName.lock";
      $handler = fopen($fileName, 'w') or die("can't open file");
      fclose($handler);
   }
   
   protected function _unlock($methodName)
   {
      $methodName = str_replace('\\', '.', $methodName);
      
      $fileName = "data/tmp/$methodName.lock";
      unlink($fileName);
   }
}