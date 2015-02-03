<?php
namespace Freemium\Model;

use Zend, Com, Freemium;

class Instance extends Com\Model\AbstractModel
{
   
   /**
   *
   * @param Zend\Stdlib\Parameters $params
   * @var string email
   * @var string domain
   * @var string password
   * @var string first_name
   * @var string last_name
   *
   */
   function doCreate(Zend\Stdlib\Parameters $params)
   {
        // check required fields
        $fields = array(
            'email',
            'domain',
            'password',
            'first_name',
            'last_name',
        );
        
        $this->hasEmptyValues($fields, $params);
        
        $sl = $this->getServiceLocator();
        
        try
        {
            $dbClient = $sl->get('Freemium\Db\Client');
            $dbDatabase = $sl->get('Freemium\Db\Database');
            $config = $sl->get('config');
            
            $topDomain = $config['freemium']['top_domain'];
            $domain = "{$params->domain}.$topDomain";
            $website = "http://{$domain}";
            
            // checm email
            $vEmail = new Zend\Validator\EmailAddress();
            
            if(!$vEmail->isValid($params->email))
            {
                $this->getCommunicator()->addError('Please provide a valid email address', 'email');
            }
            else
            {
                // check if already exist registered users with the given email
                $where = array();
                $where['email = ?'] = $params->email;
                if($dbClient->count($where))
                {
                    $this->getCommunicator()->addError('Already exist a user with the given email address', 'email');
                }
            }
            
            // check the domain name
            $strlen = strlen($params->domain);
            if(($strlen < 4 || $strlen > 20))
            {
                $this->getCommunicator()->addError('Your domain name should be between 5 and 20 characters length', 'domain');
            }
            else
            {
                if(!$this->_isValidDomainName($params->domain))
                {
                    $this->getCommunicator()->addError('Invalid domain name. Only allow letters from a to z', 'domain');
                }
                else
                {
                    // check if already exist registered users with the given domain
                    $where = array();
                    $where['domain = ?'] = $domain;
                    if($dbClient->count($where))
                    {
                        $this->getCommunicator()->addError("The given domain name it's not available", 'domain');
                    }
                }
            }
            
            if($this->isSuccess())
            {
                $cp = $sl->get('cPanelApi');
                
                $cpUser = $cp->get_user();
                $result = $cp->park($cpUser, $domain, null);
                
                $apiResponse = new Freemium\Cpanel\ApiResponse($result);
                if($apiResponse->isError())
                {
                    $err = $apiResponse->getError();
                    if(stripos($err, 'already exists') !== false)
                    {
                        $this->getCommunicator()->addError('The choosen domain name is not avaiable.');
                    }
                    else
                    {
                        $this->getCommunicator()->addError($err);
                    }
                }
                else
                {
                    // time to add the client information
                    $data = array();
                    $data['email'] = $params->email;
                    $data['password'] = $params->password;
                    $data['domain'] = $domain;
                    $data['first_name'] = $params->first_name;
                    $data['last_name'] = $params->last_name;
                    
                    $dbClient->doInsert($data);
                    $clientId = $dbClient->getLastInsertValue();
                    
                    $rowDb = $dbDatabase->findFreeDatabase();
                    
                    // ups, no free database found
                    if(!$rowDb)
                    {
                        $this->getCommunicator()->addError('There was an unexpected error, please try again in a few minutes');
                    }
                    else
                    {
                        // ok reserve the database
                        $data = array('client_id' => $clientId);

                        $where = array();
                        $where = array('id' => $rowDb->id);

                        $dbDatabase->doUpdate($data, $where);
                        $dbName = $rowDb->db_name;
                        
                        // update moodle password
                        require_once 'vendor/3rdParty/moodle/moodlelib.php';
                        require_once 'vendor/3rdParty/moodle/password.php';
                        
                        $password = hash_internal_user_password($params->password);
            
                        $sql = "UPDATE {$dbName}.mdl_user SET `password` = '$password' WHERE `username` = 'admin'";
                        $this->getDbAdapter()->query($sql)->execute();

                        //
                        $scriptsPath = $config['freemium']['path']['scripts'];
                        $mDataPath = $config['freemium']['path']['mdata'];
                        $mDataMasterPath = $config['freemium']['path']['master_mdata'];
                        $masterSqlFile = $config['freemium']['path']['master_sql_file'];
                        $configPath = $config['freemium']['path']['config'];

                        $cpanelUser = $config['freemium']['cpanel']['username'];
                        $cpanelPass = $config['freemium']['cpanel']['password'];

                        $dbPrefix =  $config['freemium']['db']['prefix'];
                        $dbUser =  $config['freemium']['db']['user'];
                        $dbHost =  $config['freemium']['db']['host'];
                        $dbPassword =  $config['freemium']['db']['password'];

                        //create mdata folder
                        $newUmask = 0777;
                        $oldUmask = umask($newUmask);

                        mkdir("$mDataPath/$domain", $newUmask, true);
                        chmod("$mDataPath/$domain", $newUmask);

                        // Copying from master data folder
                        exec("cp -Rf {$mDataMasterPath}/* {$mDataPath}/$domain/");

                        // Changing owner for the data folder
                        exec("chown -R {$cpanelUser}:{$cpanelUser} {$mDataPath}/{$domain} -R");
                        exec("chmod 777 {$mDataPath}/{$domain} -R");

                        // creating config file
                        $configStr = file_get_contents('data/config.template');
                        $configStr = str_replace('{$dbHost}', $dbHost, $configStr);
                        $configStr = str_replace('{$dbName}', $dbName, $configStr);
                        $configStr = str_replace('{$dbUser}', $dbUser, $configStr);
                        $configStr = str_replace('{$dbPassword}', $dbPassword, $configStr);
                        $configStr = str_replace('{$domain}', $domain, $configStr);
                        $configStr = str_replace('{$dataPath}', "{$mDataPath}/{$domain}", $configStr);

                        $configFilename = "{$configPath}/{$domain}.php";
                        $handlder = fopen($configFilename, 'w');
                        fwrite($handlder, $configStr);
                        fclose($handlder);
            
                        exec("chown {$cpanelUser}:{$cpanelUser} $configFilename & chmod 755 $configFilename");
                        
                        // ok done
                        $this->getCommunicator()->setSuccess("Done, you can now login to <a href='$website' target='_blank'>$website</a>.");
                        $this->getCommunicator()->addData($website, 'website');

                                            
                        // lets check how many free databases we have
                        // If we have few databases it's time to create more
                        
                        $min = $config['freemium']['min_databases_trigger'];
                        if($dbDatabase->countFree() < $min)
                        {
                            $max = $config['freemium']['max_databases'];
                            
                            $publicDir = PUBLIC_DIRECTORY;
                            $command = "php {$publicDir}/index.php create-databases $max";
                            
                            shell_exec(sprintf('%s > /dev/null 2>&1 &', $command));
                        }
                    }
                }
            }
        }
        catch(\Exception $e)
        {
            $this->setException($e);
        }
        
        return $this->isSuccess();
   }
   
   
   protected function _isValidDomainName($domainName)
   {
      return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domainName) //valid chars check
         && preg_match("/^.{1,253}$/", $domainName) //overall length check
         && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domainName)   ); //length of each label
   }
   
}