<?php
namespace Front\Controller;

use Zend, Com;

class IndexController extends Com\Controller\AbstractController
{

    function homeAction()
    {
        $request = $this->getRequest();
        
        $sl = $this->getServiceLocator();
        
        if($request->isPost())
        {
            $post = array_merge_recursive($request->getPost()->toArray(), $request->getFiles()->toArray());
            $params = new Zend\Stdlib\Parameters($post);
            
            #ini_set('display_errors', 1);
            #error_reporting(E_ALL);
            
            $mInstance = $sl->get('App\Model\Freemium\Instance');
            $flag = $mInstance->canReserve($params);
            
            $com = $mInstance->getCommunicator();
            $this->assign($params);
            
            if($flag)
            {
                $flag = $mInstance->doReserve($params);
                $com = $mInstance->getCommunicator();
                $this->setCommunicator($com);
                
                if($flag)
                {
                    $view = new Zend\View\Model\ViewModel($this->viewVars);
                    $view->setTemplate('front/index/pending');
                    return $view;
                }
            }
                        
            $this->setCommunicator($com);
            $this->assign('is_post', true);
        }
        
        $this->assign('route_name', 'home');
        
        return $this->viewVars;
    }
    
    
    function internalAction()
    {
        $request = $this->getRequest();
        
        $sl = $this->getServiceLocator();
        
        if($request->isPost())
        {
            $post = array_merge_recursive($request->getPost()->toArray(), $request->getFiles()->toArray());
            $params = new Zend\Stdlib\Parameters($post);
            
            #ini_set('display_errors', 1);
            #error_reporting(E_ALL);
            
            $mInstance = $sl->get('App\Model\Freemium\Instance');
            $flag = $mInstance->canReserve($params);
            
            $com = $mInstance->getCommunicator();
            $this->assign($params);
            
            if($flag)
            {
                $flag = $mInstance->doCreate($params);
                $com = $mInstance->getCommunicator();
                
                if($flag)
                {
                    $view = new Zend\View\Model\ViewModel($this->viewVars);
                    $view->setTemplate('front/index/thanks');
                    return $view;
                }
            }
                        
            $this->setCommunicator($com);
            $this->assign('is_post', true);
        }
        
        $this->assign('internal', 1);
        $this->assign('route_name', 'internal');
        
        $view = new Zend\View\Model\ViewModel($this->viewVars);
        $view->setTemplate('front/index/home');
        return $view;
    }
    
    
    function runCronAction()
    {
        //  We are going to execute via ajax all the crons
        
        // This action should be executed using wkhtmltopdf
        // the reason for this is because we are using javascript
        // and wkhtmltopdf can ejecute javascript
        
        $this->layout('layout/blank');
        
        $sl = $this->getServiceLocator();
        
        $dbClient = $sl->get('App\Db\Client');
        
        $where = array();
        $where['deleted = ?'] = 0;
        $where['approved = ?'] = 1;
        $where['email_verified = ?'] = 1;
        
        $rowset = $dbClient->findby($where);
        $this->assign('instances', $rowset);
        
        return $this->viewVars;
    }
    
    
    function testAction()
    {
        $view = new Zend\View\Model\ViewModel($this->viewVars);
        $view->setTemplate('front/index/thanks');
        return $view;
        
        /*
        $sl = $this->getServiceLocator();
        
        $cp = $sl->get('cPanelApi');
                
        $domain = null;
        $cpUser = $cp->get_user();
        $result = $cp->listparkeddomains($cpUser, $domain);
        
        echo '<pre>';
        print_r($result);
    
        exit;
        */
    }
}