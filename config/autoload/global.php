<?php
return array(
    'service_manager' => array(
        'factories' => array(
            'Zend\Db\Adapter\Adapter' => function ($sm)
            {
                $adapter = new \Zend\Db\Adapter\Adapter(array(
                    'driver' => 'mysqli',
                    'database' => 'paradiso_admin',//'freemium',
                    'username' => 'paradisolms', //'root',
                    'password' => 'Paradiso123', //
                    'hostname' => 'localhost', // 'localhost'
                    'profiler' => true,
                    'charset' => 'UTF8',
                    'options' => array(
                        'buffer_results' => true 
                    ) 
                ));

                return $adapter;
            },
        ),
        'aliases' => array(
            'adapter' => 'Zend\Db\Adapter\Adapter'
        )
    ),
    
    'phpSettings' => array(
        'display_startup_errors' => false,
        'display_errors' => false,
        'max_execution_time' => 60,
        'date.timezone' => 'America/La_Paz',
    ),
);