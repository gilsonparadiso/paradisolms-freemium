<?php

namespace Backend\Controller;

use Zend, Com, App;


class InstanceController extends Com\Controller\BackendController
{

    function listAction()
    {
        $sl = $this->getServiceLocator();

        $this->assign('grid_title', "Instances");

        $grid = new App\DataGrid\Client($sl, $this->viewVars);
        $view = $grid->render();                
        
        $colorBoxView = new Zend\View\Model\ViewModel();
        $colorBoxView->setTemplate('backend/instance/list.phtml');
        
        $view->addChild($colorBoxView, 'after_title');
        
        return $view;
    }
    
    
    
    function deleteAction()
    {
        exit;
    }
    
    
    function infoAction()
    {
        $this->layout('layout/blank');
        
        // client id
        $id = $this->_params('id', 0);
        $sl = $this->getServiceLocator();
        
        $dbClient = $sl->get('App\Db\Client');
        $dbDatabase = $sl->get('App\Db\Database');
        
        $rowClient = $dbClient->findByPrimaryKey($id);
        $result = $dbDatabase->findDatabaseByClientId($id);
        
        if($rowClient && $result->count())
        {
            $rowDb = $result->current();
            
            $client = new App\Lms\Services\Client();
            $client->setServicesToken($rowDb->db_name);
            
            $client->setServerUri("http://{$rowClient->domain}/services/index.php");
            $response = $client->request('count_users');
            
            if($response->isError())
            {
                $this->getCommunicator()->addError($response->getMessage());
            }
            else
            {
                $this->assign($response->getParams());
            }
        }
        else
        {
            if(!$rowClient)
            {
                $this->getCommunicator()->addError('Client not found');
            }
            else
            {
                $this->getCommunicator()->addError('The client do not have an assigned database.');
            }
        }
        
        
        return $this->viewVars;
    }
}