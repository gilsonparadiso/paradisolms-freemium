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
            
            $mUser = $sl->get('Com\Model\User');
            
            $identity = $mUser->setInitGroup();
            
            $redirects[Com\Model\Enums\Group::ALEKIA] = 'dashboard-backend';
            $redirects[Com\Model\Enums\Group::COMPANY] = 'dashboard-company';
            $redirects[Com\Model\Enums\Group::JOB_SEEKER] = 'job-seeker';
            $redirects[Com\Model\Enums\Group::ADVERTISER] = 'dashboard-advertiser';
            
            if(isset($identity['group_id']))
            {
                $groupId = $identity['group_id'];
                
                if(Com\Model\Enums\Group::COMPANY == $groupId)
                {
                    $mCompany = $sl->get('Com\Model\Company\Company');
                    $identity = $mCompany->setInitCompany();
                }
                
                if(isset($redirects[$groupId]))
                {
                    if ($back)
                    {
                        $session->back = null;
                        return $this->redirect()->toUrl($back);
                    }
                    
                    return $this->redirect()->toRoute($redirects[$groupId]);
                }
                else
                {
                    if ($back)
                    {
                        $session->back = null;
                        return $this->redirect()->toUrl($back);
                    }
                    
                    // TODO
                    // verificar a donde redireccionar al usuario en caso de no encontrar ruta definida
                    throw new \Exception('Ups!!!!!');
                }
            }
            else
            {
                // Por alguna razon el usuario no pertenece a ningun grupo.
                // No deberia iniciar session,
                $this->redirect()->toRoute('logout');
            }
        }
        else
        {
            return $this->redirect()->toRoute('login');
        }
    }
}
