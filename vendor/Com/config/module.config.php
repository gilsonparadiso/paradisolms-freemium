<?php
return array(

    // configuraciones de la autenticacion basica
    // Especial para proteger de forma basica cualquier action
    'basic-authentication' => array(
        'default' => array(
            'auth_adapter' => array(
                'config' => array(
                    'accept_schemes' => 'basic',
                    'realm' => 'paradisosolutions.com' 
                ),
                'basic_passwd_file' => 'config/htpasswd/default',
                'allow_from' => array() 
            ) 
        ),
        'webservices' => array(
            'auth_adapter' => array(
                'config' => array(
                    'accept_schemes' => 'basic',
                    'realm' => 'paradisosolutions.bo' 
                ),
                'basic_passwd_file' => 'config/htpasswd/webservices',
                'allow_from' => array(/*'place here allowed ip addresses'*/),
                
                'headers' => array(
                    'Allow-Origin' => '*' 
                ) 
            ) 
        ) 
    ),
    
   'freemium' => array(
   
      'top_domain' => 'paradisolms.com',
      
      'min_databases' => 10, // we this value to know if i'ts time to create more databases
      'max_databases' => 20, // this is the amount of databases to be
      
      'path' => array(
         'mdata' => '/home/paradisolms/mdata',
         'master_mdata' => '/home/paradisolms/mdata/master_data',
         'master_mdata_trial' => '/home/paradisolms/trial/mdata/master_data',
         'config' => '/home/paradisolms/clients/config',
         'master_sql_file' => '/home/paradisolms/repo/db/master_freemium.sql',
         'master_sql_file_trial' => '/home/paradisolms/repo/db/master_trial.sql',
      ),
      
      'db' => array(
         'host' => 'localhost',
         'prefix' => 'paradiso_',
         'user' => 'paradiso_user',
         'password' => 'Paradiso123',
      ),
      
      'cpanel' => array(
         'server' => '65.111.187.33',
         'username' => 'paradisolms',
         'password' => 'Paradiso123',
      ),
   ),
    

    'caches' => array(
        'cache-fs1' => array(
            'adapter' => array(
                'name' => 'filesystem',
                'options' => array(
                    'cache_dir' => 'data/cache/',
                    'DirPermission' => '0777',
                    'filePermission' => '0644',
                    'namespaceSeparator' => '-cache-fs1-',
                    'umask' => true,
                    'ttl' => 24 * 3600 * 7  // 7 days
                )
            ),
            'plugins' => array(
                'serializer',
                'exception_handler' => array(
                    'throw_exceptions' => (APP_ENV == APP_DEVELOPMENT) 
                ) 
            ) 
        ),
        'cache-fs2' => array(
            'adapter' => array(
                'name' => 'filesystem',
                'options' => array(
                    'cache_dir' => 'data/cache/',
                    'DirPermission' => '0777',
                    'filePermission' => '0644',
                    'namespaceSeparator' => '-cache-fs2-',
                    'umask' => true,
                    'ttl' => 24 * 3600 * 7  // 7 days
                 ) 
            ),
            'plugins' => array(
                'exception_handler' => array(
                    'throw_exceptions' => (APP_ENV == APP_DEVELOPMENT) 
                ) 
            ) 
        ) 
    ),
    
    'session' => array(
        'config' => array(
            'class' => 'Zend\Session\Config\SessionConfig',
            'options' => array(
                'name' => 'SID',
                'use_cookies' => true,
                'cookie_httponly' => true,
                'cookie_lifetime' => 86400, // 24 hours
                'remember_me_seconds' => 86400,
                'gc_maxlifetime' => 86400,
                'cache_expire' => 86400,
                'use_cookies' => true 
            ),
            
            'storage' => 'Zend\Session\Storage\SessionArrayStorage',
            
            'save_handler' => array(
                'idcolumn' => 'id',
                'namecolumn' => 'name',
                'datacolumn' => 'data',
                'lifetimecolumn' => 'lifetime',
                'modifiedcolumn' => 'modified' 
            ) 
        ) 
    ),
    
    'controllers' => array(
        'abstract_factories' => array(
            'Com\Service\ControllerFactory' 
        ) 
    ),
    
    'db_prefix' => '',
    
    'mail' => array(
        'transport' => array(
            'smtp1' => array(
                'options' => array(
                    'name' => 'localhost',
                    'host' => 'smtp.gmail.com',
                    'port' => 465,
                    'connection_class' => 'login',
                    'connection_config' => array(
                        'username' => 'sales@paradisosolutions.com',
                        'password' => 'Parad1s0',
                        'ssl' => 'ssl' 
                    ) 
                ) 
            ) 
        ),
        'from' => array(
            'no-reply' => array(
                'name' => 'no reply',
                'email' => 'noreply@paradisosolutions.com' 
            ),
            'sales' => array(
                'name' => 'Paradiso Solutions - Sales',
                'email' => 'sales@paradisosolutions.com' 
            ) 
        ) 
    ),
    
    'service_manager' => array(
        'abstract_factories' => array(
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Log\LoggerAbstractServiceFactory',
            'Com\Service\CommonFactory' 
        ),
        
        'factories' => array(
            'Zend\Session\Container' => function ($sl)
            {
                $sessionManager = $sl->get('Zend\Session\SessionManager');
                $session = new Zend\Session\Container('Default', $sessionManager);
                return $session;
            },
            
            'cPanelApi' => function ($sl)
            {
               require_once 'vendor/3rdParty/xmlapi.php';
 
               $config = $sl->get('config');

               $server = $config['freemium']['cpanel']['server'];
               $username = $config['freemium']['cpanel']['username'];
               $password = $config['freemium']['cpanel']['password'];
               
               $xmlapi = new xmlapi($server);
               $xmlapi->set_port(2083);
               $xmlapi->set_user($username);
               $xmlapi->set_password($password);
               // $xmlapi->set_debug(1); 
               $xmlapi->set_output('array');

               return $xmlapi;
            },
            
            'Com\Json\Service\Consumer' => function ($sl)
            {
                $config = $sl->get('config');
                
                $server = $config['json-client-v1']['server'];
                $username = $config['json-client-v1']['username'];
                $password = $config['json-client-v1']['password'];
                
                $consummer = new Com\Json\Service\Consumer();
                $consummer->setServer($server);
                $consummer->setUsername($username);
                $consummer->setPassword($password);
                
                return $consummer;
            },
            
            'Zend\Session\SessionManager' => function ($serviceManager)
            {
                $config = $serviceManager->get('config');
                
                if(isset($config['session']))
                {
                    $session = $config['session'];
                    
                    $sessionConfig = null;
                    if(isset($session['config']))
                    {
                        $class = isset($session['config']['class']) ? $session['config']['class'] : 'Zend\Session\Config\SessionConfig';
                        $options = isset($session['config']['options']) ? $session['config']['options'] : array();
                        
                        $sessionConfig = new $class();
                        $sessionConfig->setOptions($options);
                        
                        // storage
                        $sessionStorage = null;
                        if(isset($session['config']['storage']))
                        {
                            $class = $session['config']['storage'];
                            $sessionStorage = new $class();
                        }
                        
                        $sessionSaveHandler = null;
                        if(isset($session['config']['save_handler']))
                        {
                            $dbAdapter = $serviceManager->get('adapter');
                            
                            $sessionOptions = new Zend\Session\SaveHandler\DbTableGatewayOptions($session['config']['save_handler']);
                            
                            // crate the TableGateway object specifying the table name
                            $dbSession = new App\Db\Session();
                            $dbSession->setAdapter($dbAdapter);
                            $sessionSaveHandler = new Zend\Session\SaveHandler\DbTableGateway($dbSession, $sessionOptions);
                        }
                        
                        $sessionManager = new Zend\Session\SessionManager($sessionConfig, $sessionStorage, $sessionSaveHandler);
                    }
                    else
                    {
                        $sessionManager = new Zend\Session\SessionManager();
                    }
                }
                else
                {
                    $sessionManager = new Zend\Session\SessionManager();
                }
                
                return $sessionManager;
            } 
        ),
        
        'initializers' => array(
            function ($instance, \Zend\ServiceManager\ServiceManager $sm)
            {
                if($instance instanceof \Com\Model\AbstractModel)
                {
                    $instance->setServiceLocator($sm);
                    
                    $adapter = $sm->get('adapter');
                    $instance->setDbAdapter($adapter);
                }
                
                if($instance instanceof \Zend\Db\Adapter\AdapterAwareInterface)
                {
                    $instance->setServiceLocator($sm);
                    $adapter = $sm->get('adapter');
                    
                    $instance->setDbAdapter($adapter);
                }
                
                if($instance instanceof \Com\Db\AbstractDb)
                {
                    $adapterKey = $instance->getAdpaterKey();
                    
                    if($adapterKey)
                    {
                        $adapter = $sm->get($adapterKey);
                    }
                    else
                    {
                        $adapter = $sm->get('adapter');
                    }
                    
                    $instance->setServiceLocator($sm);
                    $instance->setAdapter($adapter);
                    $instance->initialize();
                    
                    $entityClassName = $instance->getEntityClassName();
                    if($entityClassName)
                    {
                        $instance->getResultSetPrototype()->setArrayObjectPrototype($sm->get($entityClassName));
                    }
                }
            } 
        ),
        
        'aliases' => array(
            'translator' => 'MvcTranslator',
            'session' => 'Zend\Session\Container' 
        ) 
    ),
    
    'translator' => array(
        'locale' => 'en_US',
        'translation_file_patterns' => array(
            array(
                'type' => 'phpArray',
                'base_dir' => 'language',
                'pattern' => '%s.php',
                'text_domain' => 'default' 
            ),
            /*array(
                'type'          => 'phpArray',
                'base_dir'      => 'vendor/zendframework/zendframework/resources/languages',
                'pattern'       => '%s/Zend_Captcha.php',
                'text_domain'   => 'zend_validate',
            ),
            array(
                'type'          => 'phpArray',
                'base_dir'      => 'vendor/zendframework/zendframework/resources/languages',
                'pattern'       => '%s/Zend_Validate.php',
                'text_domain'   => 'zend_validate',
            ),*/
        ) 
    ),
    
    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'doctype' => 'HTML5',
        
        'not_found_template' => 'error/404',
        'exception_template' => 'error/index',
        
        'template_map' => array(
            'layout/layout' => __DIR__ . '/../view/layout/layout.phtml',
            'layout/backend' => __DIR__ . '/../view/layout/backend.phtml',
            
            'error/404' => __DIR__ . '/../view/error/404.phtml',
            'error/index' => __DIR__ . '/../view/error/index.phtml' 
        ),
        
        'template_path_stack' => array(
            __DIR__ . '/../view' 
        ) 
    ),
    
    'view_helpers' => array(
        'abstract_factories' => array(
            'Com\Service\CommonViewHelpers' 
        ),
    ) 
);
