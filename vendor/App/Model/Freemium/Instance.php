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
   * @var string instance
   * @var string first_name
   * @var string last_name
   * @var type string
   * @var array logo
   * @var bool resize_logo
   *
   * @return bool
   */
   function doCreate(Zend\Stdlib\Parameters $params)
   {
        // check required fields
        $fields = array(
            'email',
            'password',
            'instance',
            'first_name',
            'last_name',
        );
        
        $this->hasEmptyValues($fields, $params);
        
        $sl = $this->getServiceLocator();
        
        try
        {
            $dbClient = $sl->get('App\Db\Client');
            $dbClientHasDb = $sl->get('App\Db\Client\HasDatabase');
            $dbDatabase = $sl->get('App\Db\Database');
            $dbBlacklistDomain = $sl->get('App\Db\BlacklistDomain');
            $config = $sl->get('config');
            
            if($params->type != 'freemium' && $params->type != 'trial')
                $params->type = 'freemium';

            $isTrial = ('trial' == $params->type);
            
            $params->email = strtolower($params->email);
            $params->instance = trim($params->instance);
            
            // check if the email field looks like a real email address
            $vEmail = new Zend\Validator\EmailAddress();
            if(!$vEmail->isValid($params->email))
            {
                $this->getCommunicator()->addError($this->_('provide_valid_email'), 'email');
            }
            else
            {
                // check if already exist registered users with the given email address
                $where = array();
                $where['email = ?'] = $params->email;
                if($dbClient->count($where))
                {
                    $this->getCommunicator()->addError($this->_('user_email_already_exist'), 'email');
                }
            }
            
            // all good so far, now we continue with the validations
            // Here we check stuff related to the domain and instance name
            if($this->isSuccess())
            {
                if(!preg_match('/[a-z0-9\-]/i', $params->instance))
                {
                    $this->getCommunicator()->addError($this->_('invalid_characters_instance_name'), 'email');
                    return false;
                }

                //
                $isParadisoDomain = $this->_isParadisoDomain($params->email);
                $topDomain = $config['freemium']['top_domain'];
                $domain = "{$params->instance}.$topDomain";
                $website = "http://{$domain}";
                    
                // check if the domain of the email is allowed to create account
                $exploded = explode('@', $params->email);
                $emailDomain = $exploded[1];
        
                $where = array();
                $where['domain = ?'] = $emailDomain;
                if($dbBlacklistDomain->count($where))
                {
                    $this->getCommunicator()->addError($this->_('email_address_not_allowed'), 'email');
                    return false;
                }
                
                // check if the user can provide a custom instance name
                // Have in mind that paradiso people can provide instance names
                if(!$isParadisoDomain)
                {
                    if(!$this->canEditInstanceName($params->email) && $this->instanceNameEdited($params->email, $params->instance))
                    {
                        $this->getCommunicator()->addError($this->_('not_allowed_to_edit_instance_name'), 'instance');
                    }
                }
                
                // check if the domain name is good
                if(!$this->_isValidDomainName($domain))
                {
                    $this->getCommunicator()->addError($this->_('invalid_email_address'), 'email');
                }
            }
            
            // find the a free database
            $rowDb = $dbDatabase->findFreeDatabase();                        
            // ups, no free database found
            if(!$rowDb)
            {
                $this->getCommunicator()->addError($this->_('unexpected_error'));
                $this->_createDatabasesScript();
                return false;
            }
            
            
            //
            if($this->isSuccess())
            {
                // check if already exist registered users with the domain name
                $where = array();
                $where['domain = ?'] = $domain;
                $rowClient = $dbClient->findBy($where, array(), null, 1)->current();
                if($rowClient)
                {
                    if($isParadisoDomain)
                    {
                        $rowClient = null;
                        do
                        {
                            $str = str_replace('.com', '', $params->instance);
                            $str .= mt_rand(1, 9000000);
                            
                            $domain = "$str.$topDomain";
                            $website = "http://{$domain}";
                            
                            $where = array();
                            $where['domain = ?'] = $domain;
                        }
                        while($dbClient->count($where) > 0);
                    }
                }
                
                
                $logoFile = null;
                $logoExtension = null;
                
                // we only allow logo if is a new instance name
                if(!$rowClient)
                {
                    if($params->logo)
                    {
                        $name = isset($params->logo['name']) ? $params->logo['name'] : null;
                        $type = isset($params->logo['type']) ? $params->logo['type'] : null;
                        $size = isset($params->logo['size']) ? $params->logo['size'] : null;
                        $tmpName = isset($params->logo['tmp_name']) ? $params->logo['tmp_name'] : null;
                        $error = isset($params->logo['error']) ? $params->logo['error']: null;
                        
                        $postedFile = new Com\PostedFile($name, $type, $size, $tmpName, $error);
                        if($params->resize_logo && !$postedFile->hasFile())
                        {
                            $this->getCommunicator()->addError($this->_('fix_the_below_error'));
                            $this->getCommunicator()->addError($this->_('no_logo_to_rezise'), 'logo');
                            return false;
                        }
                        
                        if($postedFile->hasFile())
                        {
                            $this->fileMimeType = Com\Func\File::getMimeType($postedFile->getTmpName());
                            
                            $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif', 'svg');
                            $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/svg+xml');
                            
                            // verificar la extension del archivo
                            if(! $this->_checkExtensionAndType($allowedExtensions, $allowedTypes, $postedFile))
                            {
                                $this->getCommunicator()->addError($this->_('fix_the_below_error'));
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
                                    if($params->resize_logo)
                                    {
                                        $this->_resizeLogo($logoFile);
                                    }
                                    
                                    $pathinfo = pathinfo($logoFile);
                                    $logoExtension = $pathinfo['extension'];
                                }
                                else
                                {
                                    $this->getCommunicator()->addError($this->_('fix_the_below_error'));
                                    $this->getCommunicator()->addError($this->_('error_uploading_logo'), 'logo');
                                    return false;
                                }
                            }
                            else
                            {
                                $this->getCommunicator()->addError($this->_('fix_the_below_error'));
                                
                                $errorMessage = $fileSaver->getCommunicator()->getErrors();
                                $this->getCommunicator()->addError($errorMessage[0], 'logo');
                                return false;
                            }
                        }
                    }
                }
                
                
                $cp = $sl->get('cPanelApi');
                
                //
                if($isTrial)
                {
                    $mDataMasterPath = $config['freemium']['path']['master_mdata_trial'];
                    $masterSqlFile = $config['freemium']['path']['master_sql_file_trial'];
                }
                else
                {
                    $mDataMasterPath = $config['freemium']['path']['master_mdata'];
                    $masterSqlFile = $config['freemium']['path']['master_sql_file'];
                }
                
                $mDataPath = $config['freemium']['path']['mdata'];
                $configPath = $config['freemium']['path']['config'];

                $cpanelUser = $config['freemium']['cpanel']['username'];
                $cpanelPass = $config['freemium']['cpanel']['password'];

                $dbPrefix =  $config['freemium']['db']['prefix'];
                $dbUser =  $config['freemium']['db']['user'];
                $dbHost =  $config['freemium']['db']['host'];
                $dbPassword =  $config['freemium']['db']['password'];
                
                // if is a client from an already registered domain then we should add the client as a user of the existing instance.
                // if is a client and the domain is not registered then we procced to create a new instance
                if(!$rowClient)
                {
                    $cpUser = $cp->get_user();
                    $result = $cp->park($cpUser, $domain, null);
                
                    $apiResponse = new App\Cpanel\ApiResponse($result);
                    
                    if($apiResponse->isError())
                    {
                        $err = $apiResponse->getError();
                        if(stripos($err, 'already exists') !== false)
                        {
                            $this->getCommunicator()->addError($this->_('domain_name_already_registered'));
                            return false;
                        }
                        else
                        {
                            $this->getCommunicator()->addError($err);
                            return false;
                        }
                    }
                }
                
                
                // time to add the client information
                $data = array();
                $data['email'] = $params->email;
                $data['password'] = $params->password;
                $data['domain'] = $domain;
                $data['first_name'] = $params->first_name;
                $data['last_name'] = $params->last_name;
                $data['created_on'] = date('Y-m-d H:i:s');
                $data['email_verified'] = $isTrial ? 1 : 0;
                $data['type'] = $params->type;
                
                $dbClient->doInsert($data);
                $clientId = $dbClient->getLastInsertValue();
                
                // 
                require_once 'vendor/3rdParty/moodle/moodlelib.php';
                require_once 'vendor/3rdParty/moodle/password.php';
                
                
                // new domain so we assign a dababase
                if(!$rowClient)
                {
                    if($isTrial)
                    {
                        // is trial user
                        // we need to load different database
                        $response = $cp->api2_query($cpanelUser, 'MysqlFE', 'deletedb', array(
                            'db' => $rowDb->db_name,
                        ));
                        
                        if(isset($response['error']) || isset($response['event']['error']))
                        {
                            $err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                            throw new \Exception($err);
                        }
                        
                        
                        /*************************************/
                        // create the database
                        /*************************************/
                        $response = $cp->api2_query($cpanelUser, 'MysqlFE', 'createdb', array(
                            'db' => $rowDb->db_name,
                        ));

                        if(isset($response['error']) || isset($response['event']['error']))
                        {
                            $err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                            throw new \Exception($err);
                        }
                        
                        
                        /*******************************/
                        // update database schema
                        /*******************************/
                        $adapter = $sl->get('adapter');
                        $sql = "ALTER SCHEMA `{$rowDb->db_name}`  DEFAULT CHARACTER SET utf8  DEFAULT COLLATE utf8_general_ci \n";
                        $statement = $adapter->query($sql, 'execute');
                        
                        /*******************************/
                        // Assign user to db
                        /*******************************/
                        $dbUserName = 'user';
                        $response = $cp->api2_query(CPANEL_USER, 
                            'MysqlFE', 'setdbuserprivileges',
                            array(
                                'privileges' => 'ALL_PRIVILEGES',
                                'db' => $rowDb->db_name,
                                'dbuser' => $dbUserName,
                            )
                        );
                        
                        if(isset($response['error']) || isset($response['event']['error']))
                        {
                            $err = isset($response['error']) ? $response['error'] : $response['event']['error'];
                            throw new \Exception($err);
                        }
                        
                        /*******************************/
                        // RESTORING database
                        /*******************************/
                        exec("mysql -u{$cpanelUser} -p{$cpanelPass} $rowDb->db_name < $masterSqlFile");
                    }
                
                
                    // ok reserve the database
                    $data = array(
                        'client_id' => $clientId
                        ,'database_id' => $rowDb->id
                    );
                    
                    $dbClientHasDb->doInsert($data);

                    // update credentials and user information in the lms instance
                    $dbName = $rowDb->db_name;
                    $password = hash_internal_user_password($params->password);
        
                    mysql_connect($rowDb->db_host, $rowDb->db_user, $rowDb->db_password);
                    mysql_select_db($dbName);
                    
                    $firstNname = mysql_real_escape_string($params->first_name);
                    $lastNname = mysql_real_escape_string($params->last_name);
                    
                    $confirmed = $isTrial ? 1 : 0;
                    
                    $sql = "
                    UPDATE mdl_user SET 
                        `password` = '$password'
                        ,`email` = '{$params->email}'
                        ,`username` = '{$params->email}'
                        ,`firstname` = '{$firstNname}'
                        ,`lastname` = '{$lastNname}'
                        ,`confirmed` = $confirmed
                    WHERE `id` = '2'
                    ";
                    
                    mysql_query($sql);

                    

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
                    
                    // move the logo to the mdata folder
                    if($logoFile)
                    {
                        rename($logoFile, "$mDataPath/{$domain}/logo.{$logoExtension}");
                    }
                }
                else
                {
                    $confirmed = $isTrial ? 1 : 0;
                    
                    // find the database name, we get the information from the previous registered user
                    $where = array();
                    $where['client_id = ?'] = $rowClient->id;
                    
                    $rowDb = $dbDatabase->findDatabaseByClientId($rowClient->id)->current();
                    
                    // add as a new user into the existing instance
                    $dbName = $rowDb->db_name;
                    $password = hash_internal_user_password($params->password);

                    mysql_connect($rowDb->db_host, $rowDb->db_user, $rowDb->db_password);
                    mysql_select_db($dbName);
                    
                    $firstNname = mysql_real_escape_string($params->first_name);
                    $lastNname = mysql_real_escape_string($params->last_name);
                    
                    $sql = "INSERT INTO mdl_user (`username`, `password`, `firstname`, `lastname`, `email`, ,`confirmed`) VALUES
                    ('{$params->email}', '$password', '{$firstNname}', '{$lastNname}', '$email', $confirmed)";
                    
                    mysql_query($sql);
                    
                    
                    // ok reserve the database
                    $data = array(
                        'client_id' => $rowClient->id
                        ,'database_id' => $rowDb->id
                    );
                    
                    $dbClientHasDb->doInsert($data);
                }
                
                // ok, we are done
                $this->getCommunicator()
                    ->setSuccess($this->_('freemium_account_created', array("$website/logo.php", 'Go to your instance')))
                    ->addData($website, 'website');
                    
                    
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
                
                // preparing some replacement values
                $data = array();
                $data['follow_us'] = $this->_('follow_us');
                $data['body'] = $this->_('confirm_your_email_address_body', array($url, $params->email, $params->password));
                $data['header'] = '';

                // load the email template and replace values
                $mTemplate = $sl->get('App\Model\EmailTemplate');
                $arr = $mTemplate->loadAndParse('common', $data);
                
                //
                $mailer = new Com\Mailer();
                
                // prepare the message to be send
                $message = $mailer->prepareMessage($arr['body'], null, $this->_('confirm_your_email_address_subject'));
                    
                if($isTrial)
                {
                    $message->addTo('gilson@paradisosolutions.com');
                    $message->addTo('yassir@paradisosolutions.com');
                }
                else
                {
                    $message->setTo($params->email);
                    
                    $message->addBcc('gilson@paradisosolutions.com');
                    $message->addBcc('yassir@paradisosolutions.com');
                }
                
                // prepare de mail transport and send the message
                $transport = $mailer->getTransport($message, 'smtp1', 'sales');
                $transport->send($message);
                
                $this->_createDatabasesScript();
            }
        }
        catch(\Exception $e)
        {
            $this->setException($e);
        }
        
        return $this->isSuccess();
   }
   
   
    
    
    
    /**
    * Verifica si el usuario puede propocrionar nombre de una instancia.
    * Si ya existe un usuario registrado con el mismo dominio de correo entonces no puede especifiar un
    * nombre de instancia diferente
    *
    * @param string $email
    * @return bool
    */
    function canEditInstanceName($email)
    {
        $r = false;
        
        $sl = $this->getServiceLocator();
        
        $dbClient = $sl->get('App\Db\Client');
        
        if(!empty($email))
        {
            $vEmail = new Zend\Validator\EmailAddress();
            if($vEmail->isValid($email))
            {
                if($this->_isParadisoDomain($email))
                {
                    $r = true;
                }
                else
                {
                    // basado en el nombre de dominio del email tenemos que buscar si existe algun cliente con el mismo nombre de instancia 
                    // en caso de que ya exista una instancia basada en el dominio del email del usuario, entonces esta obligado a usar ese
                    // nombre de instancia
                    
                    $exploed = explode('@', $email);
                    $emailDomain = $exploed[1];
                    
                    $exploded = explode('.', $emailDomain);
                    $instanceName = $exploded[0];
                    
                    $config = $sl->get('config');
                    $topDomain = $config['freemium']['top_domain'];
                    $domain = "$instanceName.$topDomain";
                    
                    $r = (0 == $dbClient->countByDomain($domain));
                }
            }
        }
        
        return $r;
    }
    
    
    
    
    /**
    *
    * @param string $email
    * @param string $instanceName
    * @return bool
    */
    function instanceNameEdited($email, $instanceName)
    {
        // 
        $exploed = explode('@', $email);
        $emailDomain = $exploed[1];
        
        $exploded = explode('.', $emailDomain);
        $instanceNameFromEmail = $exploded[0];
        
        //
        return ($instanceNameFromEmail != $instanceName);
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
                $dbDatabase = $sl->get('App\Db\Database');
                
                $row = $dbClient->findBy($where)->current();
                if(! $row)
                {
                    $this->getCommunicator()->addError($this->_('invalid_verification_code'));
                }
                elseif($row->email_verified)
                {
                    $this->getCommunicator()->addError($this->_('account_already_verified', array("http://{$row->domain}/logo.php")));
                }
                else
                {
                    $cPassword = new Com\Crypt\Password();
                    if(! $cPassword->validate($params->email, $params->code))
                    {
                        $this->getCommunicator()->addError($this->_('invalid_verification_code'));
                    }
                }
            }
            
            //
            if($this->isSuccess())
            {
                
                $row->email_verified = 1;
                $row->email_verified_on = date('Y-m-d H:i:s');
                
                $where = array();
                $where['id = ?'] = $row->id;
                
                $dbClient->doUpdate($row->toArray(), $where);
                
                //
                $rowset = $dbDatabase->findDatabaseByClientId($row->id);
                if($rowset->count())
                {
                    $rowDb = $rowset->current();
                    
                    $sql = "
                    UPDATE mdl_user SET 
                        `confirmed` = 1
                    WHERE `email` = '{$params->email}'
                    ";
                    
                    $cnn = mysql_connect($rowDb->db_host, $rowDb->db_user, $rowDb->db_password);
                    mysql_select_db($rowDb->db_name, $cnn);
                    mysql_query($sql, $cnn);
                }
                
                $this->getCommunicator()->setSuccess($this->_('account_verified', array("http://{$row->domain}/logo.php")));
            }
        }
        catch(\Exception $e)
        {
            $this->setException($e);
        }
        
        return $this->isSuccess();
    }
   
   
    protected function _createDatabasesScript()
    {
        // final step, lets run the cron
        $publicDir = PUBLIC_DIRECTORY;
        $coreDir = CORE_DIRECTORY;
        
        $command = "/usr/local/bin/php {$publicDir}/index.php create-databases > {$coreDir}/data/log/create-databases.cron.log 2>&1 &";
        shell_exec($command);
    }
   
    
    protected function _isParadisoDomain($email)
    {
        $exploded = explode('@', $email);
        $emailDomain = $exploded[1];
        
        $isPradisoDomain = ('paradisosolutions.com' == $emailDomain);
        
        return $isPradisoDomain;
    }
   
   
   
    protected function _isValidDomainName($domainName)
    {
        return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domainName) //valid chars check
         && preg_match("/^.{1,253}$/", $domainName) //overall length check
         && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domainName)   ); //length of each label
    }
   
   
    protected function _resizeLogo($logoFile)
    {
        // get image size
        $size = getimagesize($logoFile);
        $width = $size[0];
        $height = $size[1];
        $mime = $size['mime'];
        
        // Ratio cropping
        $offsetX = 0;
        $offsetY = 0;

        // define image max size
        $maxWidth = 300;
        $maxHeight = 79;
        
        // Determine the quality of the output image
        $quality = 100;
        
        // Setting up the ratios needed for resizing. We will compare these below to determine how to
        // resize the image (based on height or based on width)
        $xRatio = $maxWidth / $width;
        $yRatio = $maxHeight / $height;
        
        if ($xRatio * $height < $maxHeight)
        {
            // Resize the image based on width
            $tnHeight = ceil($xRatio * $height);
            $tnWidth = $maxWidth;
        }
        else // Resize the image based on height
        {
            $tnWidth = ceil($yRatio * $width);
            $tnHeight = $maxHeight;
        }
        
        // We don't want to run out of memory
        ini_set('memory_limit', "-1");
        
        // Set up a blank canvas for our resized image (destination)
        $dst = imagecreatetruecolor($tnWidth, $tnHeight);
        
        // Set up the appropriate image handling functions based on the original image's mime type
        switch ($mime) 
        {
            case 'image/gif':
            {
                // We will be converting GIFs to PNGs to avoid transparency issues when resizing GIFs
                // This is maybe not the ideal solution, but IE6 can suck it
                $creationFunction = 'ImageCreateFromGif';
                $outputFunction = 'ImagePng';
                $mime = 'image/png'; // We need to convert GIFs to PNGs
                $doSharpen = false;
                $quality = round(10 - ($quality / 10)); // We are converting the GIF to a PNG and PNG needs a compression level of 0 (no compression) through 9
                break;
            }


            case 'image/x-png':
            case 'image/png':
            {
                $creationFunction = 'ImageCreateFromPng';
                $outputFunction = 'ImagePng';
                $doSharpen = false;
                $quality = round(10 - ($quality / 10)); // PNG needs a compression level of 0 (no compression) through 9
                break;
            }

            default:
            {
                $creationFunction = 'ImageCreateFromJpeg';
                $outputFunction = 'ImageJpeg';
                $doSharpen = true;
                break;
            }
        }
        
        // Read in the original image
        $src = $creationFunction($logoFile);
        if (in_array($mime, array('image/gif','image/png')))
        {
            // If this is a GIF or a PNG, we need to set up transparency
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        
        // Resample the original image into the resized canvas we set up earlier
        imagecopyresampled($dst, $src, 0, 0, $offsetX, $offsetY, $tnWidth, $tnHeight, $width, $height);
        if ($doSharpen)
        {
            // Sharpen the image based on two things:
            // (1) the difference between the original size and the final size
            // (2) the final size
            $sharpness = $this->_findSharp($width, $tnWidth);
            $sharpenMatrix = array(array(- 1,- 2,- 1), array(- 2, $sharpness + 12, - 2), array(- 1, - 2, - 1));
            
            $divisor = $sharpness;
            $offset = 0;
            imageconvolution($dst, $sharpenMatrix, $divisor, $offset);
        }
        
        // Write the resized image to the cache
        $outputFunction($dst, $logoFile, $quality);
        
        // Clean up the memory
        ImageDestroy($src);
        ImageDestroy($dst);
    }
    
    
    protected function _findSharp($orig, $final) // function from Ryan Rud (http://adryrun.com)
    {
        $final = $final * (750.0 / $orig);
        $a = 52;
        $b = - 0.27810650887573124;
        $c = .00047337278106508946;
        
        $result = $a + $b * $final + $c * $final * $final;
        
        return max(round($result), 0);
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