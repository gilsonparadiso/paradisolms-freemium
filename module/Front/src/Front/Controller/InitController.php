<?php
namespace Front\Controller;

use Com, Zend;

class InitController extends Com\Controller\AbstractController
{
    
    
    function toDeleteAction()
    {
        // este metodo debe ser eliminado,
        $identity = $this->getUserIdentity();
        
        $view = new Zend\View\Model\ViewModel();
        $view->setTemplate('vars');
        
        $view->title = '<h1>dummy Action</h1>';
        $view->method = __METHOD__;
        
        
        $this->debug($identity, 'identity');
        
        return $view;
    }
    

    /**
     * @see https://app.asana.com/0/14725105905099/15626302371149
     */
    function initAction()
    {
        $sl = $this->getServiceLocator();
        
        $session = $sl->get('session');
        $back = $session->back;
        
        $identity = $this->getUserIdentity();

        if ($identity)
        {
            if ($back)
            {
                $session->back = null;
                return $this->redirect()->toUrl($back);
            }
            else
            {
                return $this->redirect()->toRoute('dashboard-backend');
            }
        }
        else
        {
            return $this->redirect()->toRoute('login');
        }
    }
}
