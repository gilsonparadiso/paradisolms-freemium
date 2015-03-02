<?php

namespace Backend\Controller;

use Zend, Com;
use Zend\Dom\Document;


class SqlController extends Com\Controller\BackendController
{

    function indexAction()
    {
        $sl = $this->getServiceLocator();
        $request = $this->getRequest();
        $dsFormatter = new Com\DataSourceFormatter();
        
        $dbDatabase = $sl->get('App\Db\Database');
        $databases = $dbDatabase->findAllWithClientInfo();
        
        if($request->isPost())
        {
            $params = $request->getPost();
            $database = $params->database;
            $exploded = explode("\n", $params->query);
            $result = array();
            
            if(count($exploded))
            {
                foreach($exploded as $query)
                {
                    if(!empty($query))
                    {
                        try
                        {
                            if(empty($database))
                            {
                                foreach($databases as $item)
                                {
                                    $this->_execute($item->db_name, $query);
                                }
                            }
                            else
                            {
                                $this->_execute($database, $query);
                            }
                        }
                        catch(\Exception $e)
                        {
                            $result[] = array(
                                'query' => $query
                                ,'error' => $e->getMessage()
                            );
                        }
                    }
                }
            }
            
            $this->assign('result', $result);
            $this->assign('executed', 1);
            $this->assign($params);
        }

        //
        $textField = array('%db_name% %domain%', array('%db_name%' => 'db_name', '%domain%'=>'domain'));
        $valueField = 'db_name';
        
        $ds = $dsFormatter->setDatasource($databases)->toFormSelect($textField, $valueField);
        
        $this->assign('database_ds', $ds);
        
        return $this->viewVars;
    }
    
    
    
    protected function _execute($database, $query)
    {
        $sl = $this->getServiceLocator();
        $config = $sl->get('config');
    
        $adapter = new Zend\Db\Adapter\Adapter(array(
            'driver' => 'mysqli',
            'database' => $database,
            'username' => $config['freemium']['db']['user'],
            'password' => $config['freemium']['db']['password'], //
            'hostname' => $config['freemium']['db']['host'],
            'profiler' => true,
            'charset' => 'UTF8',
            'options' => array(
                'buffer_results' => true 
            ) 
        ));
        
        $driver = $adapter->getDriver();
        $connection = $driver->getConnection();
        
        $connection->execute($query);
    }
}