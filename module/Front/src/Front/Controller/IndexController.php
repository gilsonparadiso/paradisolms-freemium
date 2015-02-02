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
            
            $com = $mInstance->getCommunicator();
            
            if($flag)
            {
                $data = $com->getData();
                $this->assign($data);
            }
            else
            {
                $this->assign($params);
            }
                        
            $this->setCommunicator($com);
            $this->assign('is_post', true);
        }

        return $this->viewVars;
   }
}