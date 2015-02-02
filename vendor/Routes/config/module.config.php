<?php
return array(

   'router' => array(
        'routes' => array(

            'home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route' => '/',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Front\Controller',
                        'controller' => 'Index',
                        'action' => 'home',
                    ) 
                ) 
            ),
            
            'ajax' => array(
                'type' => 'Zend\Mvc\Router\Http\segment',
                'options' => array(
                    'route' => '/ajax/:action',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Front\Controller',
                        'controller' => 'Ajax',
                    ) 
                ) 
            ),
        ) 
    ),
);
