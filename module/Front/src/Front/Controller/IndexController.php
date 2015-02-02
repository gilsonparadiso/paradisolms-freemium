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
            $params = $request->getPost();
            
            $mInstance = $sl->get('Freemium\Model\Instance');
            $flag = $mInstance->doCreate($params);
            
            if(!$flag)
            {
                $this->assign($params);
            }
            
            $communicator = $mInstance->getCommunicator();
            $this->setCommunicator($communicator);
            $this->assign('is_post', true);
        }

        return $this->viewVars;
   }
}