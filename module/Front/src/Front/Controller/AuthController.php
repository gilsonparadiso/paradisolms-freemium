<?php

namespace Front\Controller;

use Com, Zend;


class AuthController extends Com\Controller\AbstractController
{


    function loginAction()
    {
        $this->layout('backend');
        
        $request = $this->getRequest();
        
        $sl = $this->getServiceLocator();
        
        $auth = $sl->get('Com\Auth\Authentication');
        
        if($auth->hasIdentity())
        {
            // lets redirect to auth-init
            return $this->redirect()->toRoute('init');
        }
        else
        {
            
            if($request->isPost())
            {
                $sl = $this->getServiceLocator();
                
                $params = $request->getPost();
                
                $mUser = $sl->get('Com\Model\User');
                $flag = $mUser->login($params);
                
                if($flag)
                {
                    return $this->redirect()->toRoute('init');
                }
                else
                {
                    $com = $mUser->getCommunicator();
                    $this->setCommunicator($com);
                }
            }
        }
        
        return $this->viewVars;
    }


    function logoutAction()
    {
        $auth = new Com\Auth\Authentication();
        $auth->clearIdentity();
        
        return $this->redirect()->toRoute('login');
    }


    function verifyAccountAction()
    {
        $params = new Zend\Stdlib\Parameters($this->params()->fromRoute());
        
        $sl = $this->getServiceLocator();
        $mInstance = $sl->get('App\Model\Freemium\Instance');
        
        $flag = $mInstance->verifyAccount($params);
        
        $com = $mInstance->getCommunicator();
        $this->setCommunicator($com);
        
        return $this->viewVars;
    }

}
