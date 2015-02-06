<?php
namespace App\Model\Freemium;

use Zend, Com, App;

class Instance extends Com\Model\AbstractModel
{

    protected $fileMimeType;
    
    
   
   /**
   *
   * @param Zend\Stdlib\Parameters $params
   * @var string email
   * @var string password
   * @var string first_name
   * @var string last_name
   * @var array logo
   *
   * @return bool
   */
   function doCreate(Zend\Stdlib\Parameters $params)
   {
        // check required fields
        $fields = array(
            'email',
            'password',
            'first_name',
            'last_name',
        );
        
        $this->hasEmptyValues($fields, $params);
        
        $sl = $this->getServiceLocator();
        
        try
        {
            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');
            $dbEmailProvider = $sl->get('App\Db\EmailProvider');
            $config = $sl->get('config');
            
            
            
            
            
            
            
            
            
            
            // checm email
            $vEmail = new Zend\Validator\EmailAddress();
            
            if(!$vEmail->isValid($params->email))
            {
                $this->getCommunicator()->addError($this->_('provide_valid_email'), 'email');
            }
            else
            {
                // check if already exist registered users with the given email
                $where = array();
                $where['email = ?'] = $params->email;
                if($dbClient->count($where))
                {
                    $this->getCommunicator()->addError($this->_('user_email_already_exist'), 'email');
                }
            }
            
            // un valor que indica si el dominio ya esta registrado
            // en caso afirmativo no se debe crear una nueva instancia, solo se debe crear un
            // usuario en la instancia existente
            $rowClient = false;
            
            
            $exploded = explode('@', $params->email);
            $emailDomain = $exploded[1];
            
            // check the email provider
            $where = array();
            $where['domain = ?'] = $emailDomain;
            if($dbEmailProvider->count($where))
            {
                $this->getCommunicator()->addError($this->_('email_address_not_allowed'), 'email');
                return false;
            }
            else
            {
                $topDomain = $config['freemium']['top_domain'];
                $domain = "{$emailDomain}.$topDomain";
                $website = "http://{$domain}";
                
                // check the domain name
                if(!$this->_isValidDomainName($domain))
                {
                    $this->getCommunicator()->addError($this->_('invalid_email_address'), 'email');
                }
                else
                {
                    // check if already exist registered users with the given domain
                    $where = array();
                    $where['domain = ?'] = $domain;
                 
                    $rowClient = $dbClient->findBy($where, array(), null, 1)->current();
                }
            }
          
            $logoFile = null;
            $logoExtension = null;
            
            if($rowClient)
            {
                //
                if($params->logo)
                {
                    $name = isset($params->logo['name']) ? $params->logo['name'] : null;
                    $type = isset($params->logo['type']) ? $params->logo['type'] : null;
                    $size = isset($params->logo['size']) ? $params->logo['size'] : null;
                    $tmpName = isset($params->logo['tmp_name']) ? $params->logo['tmp_name'] : null;
                    $error = isset($params->logo['error']) ? $params->logo['error']: null;
                    
                    $postedFile = new Com\PostedFile($name, $type, $size, $tmpName, $error);
                    if($postedFile->hasFile())
                    {
                        $this->fileMimeType = Com\Func\File::getMimeType($postedFile->getTmpName());
                        
                        $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
                        $allowedTypes = array('image/jpeg', 'image/png', 'image/gif');
                        
                        // verificar la extension del archivo
                        if(! $this->_checkExtensionAndType($allowedExtensions, $allowedTypes, $postedFile))
                        {
                            $this->getCommunicator()->addError($this->_('invalid_image_type_for_logo'), 'logo');
                            return false;
                        }
                        
                        $uploadPath = PUBLIC_DIRECTORY . '/uploads';
                        
                        $fileSaver = new Com\FileSaver($postedFile);
                        $fileSaver->setEncloseWithDate(true);
                        $fileSaver->setUseRandFileName(true);
                        $fileSaver->setUploadPath($uploadPath);
                        $fileSaver->setContainerDirectory('img');
                        $fileSaver->setAllowImagesForWeb();
                        $fileSaver->setUseRandFileName(true);
                        
                        if($fileSaver->check())
                        {
                            $filename = $fileSaver->saveAs();
                            if($filename)
                            {
                                $logoFile = "{$fileSaver->getFullPathToUpload()}/$filename";
                                
                                $pathinfo = pathinfo($logoFile);
                                $logoExtension = $pathinfo['extension'];
                            }
                            else
                            {
                                $this->getCommunicator()->addError($this->_('error_uploading_logo'), 'logo');
                            }
                        }
                        else
                        {
                            $errorMessage = $fileSaver->getCommunicator()->getErrors();
                            $this->getCommunicator()->addError($errorMessage[0], 'logo');
                        }
                    }
                }
            }
            
            if($this->isSuccess())
            {
                // if is a client from an already registered domain then we should add the client as a user of the existing instance.
                // if is a client and the domain is not registered then we procced to create a new instance
                if(!$rowClient)
                {
                    $cp = $sl->get('cPanelApi');
                    
                    $cpUser = $cp->get_user();
                    $result = $cp->park($cpUser, $domain, null);
                
                    $apiResponse = new App\Cpanel\ApiResponse($result);
                    
                    if($apiResponse->isError())
                    {
                        $err = $apiResponse->getError();
                        if(stripos($err, 'already exists') !== false)
                        {
                            $this->getCommunicator()->addError($this->_('domain_name_already_registered'));
                        }
                        else
                        {
                            $this->getCommunicator()->addError($err);
                        }
                    }
                }
                
                if($this->isSuccess())
                {
                    // time to add the client information
                    $data = array();
                    $data['email'] = $params->email;
                    $data['password'] = $params->password;
                    $data['domain'] = $domain;
                    $data['first_name'] = $params->first_name;
                    $data['last_name'] = $params->last_name;
                    $data['created_on'] = date('Y-m-d H:i:s');
                    $data['email_verified'] = 0;
                    
                    $dbClient->doInsert($data);
                    $clientId = $dbClient->getLastInsertValue();
                    
                    // update admin user
                    require_once 'vendor/3rdParty/moodle/moodlelib.php';
                    require_once 'vendor/3rdParty/moodle/password.php';
                    
                    if(!$rowClient)
                    {
                        $rowDb = $dbDatabase->findFreeDatabase();                        
                        
                        
                        // ups, no free database found
                        if(!$rowDb)
                        {
                            $this->getCommunicator()->addError($this->_('unexpected_error'));
                        }
                        else
                        {
                            // ok reserve the database
                            $data = array('client_id' => $clientId);

                            $where = array();
                            $where = array('id' => $rowDb->id);

                            $dbDatabase->doUpdate($data, $where);
                            $dbName = $rowDb->db_name;
                            $password = hash_internal_user_password($params->password);
                
                            $sql = "UPDATE {$dbName}.mdl_user SET 
                                `password` = '$password'
                                ,`email` = '{$params->email}'
                                ,`firstname` = '{$params->first_name}'
                                ,`lastname` = '{$params->last_name}'
                                ,`confirmed` = 0
                            WHERE `username` = 'admin'
                            ";
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
                            
                            // move the logo
                            if($logoFile)
                            {
                                move($logoFile, "$mDataPath/logo.{$logoExtension}");
                            }
                            
                            // ok, we are done
                            $this->getCommunicator()->setSuccess($this->_('freemium_account_created', array($website)));
                            $this->getCommunicator()->addData($website, 'website');
                        }
                    }
                    else
                    {
                        // find the database name
                        $clientId = $rowClient->id;
                        $where = array();
                        $where['client_id = ?'] = $clientId;
                        
                        $rowDb = $dbDatabase->findBy($where, array(), null, 1)->current();

                        $dbName = $rowDb->db_name;
                        $password = hash_internal_user_password($params->password);
            
                        $sql = "INSERT INTO {$dbName}.mdl_user (`username`, `password`, `firstname`, `lastname`, `email`) VALUES
                        ('{$params->email}', '$password', '{$params->fist_name}', '{$params->last_name}', '$email')";
                        $this->getDbAdapter()->query($sql)->execute();
                    }
                    
                    // send the confirmation email to the user
                    $cPassword = new Com\Crypt\Password();
                    $plain = $params->email;
                    $code = $cPassword->encode($plain);

                    //
                    $request = $sl->get('request');
                    $uri = $request->getUri();
            
                    $serverUrl = "{$uri->getScheme()}://{$uri->getHost()}";
                    
                    $routeParams = array();
                    $routeParams['action'] = 'verify-account';
                    $routeParams['code'] = $code;
                    $routeParams['email'] = $params->email;
                    
                    $viewRenderer = $sl->get('ViewRenderer');
                    $url = $serverUrl . $viewRenderer->url('auth/wildcard', $routeParams);
                    
                    // preparing some replacements values
                    $data = array();
                    $data['follow_us'] = $this->_('follow_us');
                    $data['body'] = $this->_('confirm_your_email_address_body', array($url));
                    $data['header'] = '';

                    // load the email template and replace values
                    $mTemplate = $sl->get('Com\Model\EmailTemplate');
                    $arr = $mTemplate->loadAndParse('common', $data);
                    
                    //
                    $mailer = new Com\Mailer();
                    $message = $mailer->prepareMessage($arr['body'], null, $this->_('confirm_your_email_address_subject'));
                    $message->setTo($params->email);

                    $transport = $mailer->getTransport($message, 'smtp1');
                    $transport->send($message);
                    
                    
                    
                    
                    
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
        catch(\Exception $e)
        {
            $this->setException($e);
        }
        
        return $this->isSuccess();
   }
   
   
   
   /**
     *
     * @param Zend\Stdlib\Parameters $params
     * @var string email
     *
     * @return boolean
     */
    function verifyAccount(Zend\Stdlib\Parameters $params)
    {
        $vEmail = new Zend\Validator\EmailAddress();
        
        if(! $vEmail->isValid($params->email))
        {
            $this->getCommunicator()->addError($this->_('invalid_email_address'), 'email');
        }
        
        $sl = $this->getServiceLocator();
        
        try
        {
            if($this->isSuccess())
            {
                // lets look for the same email in the database
                $where = array();
                $where['email = ?'] = $params->email;
                
                $dbClient = $sl->get('App\Db\Client');
                
                $row = $dbClient->findBy($where)->current();
                if(! $row)
                {
                    $this->getCommunicator()->addError($this->_('invalid_verification_code'));
                }
                elseif($row->email_verified)
                {
                    $this->getCommunicator()->addError($this->_('account_already_verified'));
                }
                else
                {
                    $cPassword = new Com\Crypt\Password();
                    if(! $cPassword->validate($email, $params->code))
                    {
                        $this->getCommunicator()->addError($this->_('invalid_verification_code'));
                    }
                }
                
                //
                if($this->isSuccess())
                {
                    $row->email_verified = 1;
                    $row->email_verified_on = date('Y-m-d H:i:s');
                    
                    $where = array();
                    $where['id = ?'] = $row->id;
                    
                    $dbUser->doUpdate($row->toArray(), $where);
                    
                    $this->getCommunicator()->setSuccess($this->_('account_verified', array("http://{$row->domain}")));
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
   
   
   /**
     *
     * @param array $allowedExtensions
     * @param array $allowedTypes
     * @param \Com\PostedFile $postedFile
     * @return bool
     */
    protected function _checkExtensionAndType(array $allowedExtensions, array $allowedTypes,\Com\PostedFile $postedFile)
    {
        $result = true;
        
        if($postedFile->hasFile())
        {
            
            // verificar la extension del archivo
            if(count($allowedExtensions))
            {
                if(! $postedFile->hasExtension($allowedExtensions))
                {
                    $result = false;
                }
            }
            
            if(count($allowedTypes))
            {
                $patterns = array();
                
                foreach($allowedTypes as $value)
                {
                    $patterns[] = '^(' . $value . ')$';
                }
                
                $pattern = implode('|', $patterns);
                
                if(! preg_match("#$pattern#i", $this->fileMimeType))
                {
                    $result = false;
                }
            }
        }
        
        return $result;
    }
   
}