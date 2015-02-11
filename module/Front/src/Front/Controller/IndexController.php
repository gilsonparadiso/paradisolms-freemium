<?php
namespace Front\Controller;

use Zend, Com;

class IndexController extends Com\Controller\AbstractController
{

    function homeAction()
    {
        $request = $this->getRequest();
        
        $sl = $this->getServiceLocator();
        $type = $this->params()->fromQuery('type', 'freemium');
        
        if($request->isPost())
        {
            $post = array_merge_recursive($request->getPost()->toArray(), $request->getFiles()->toArray());
            $params = new Zend\Stdlib\Parameters($post);
            
            $params->type = $type;
            
            $mInstance = $sl->get('App\Model\Freemium\Instance');
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
        
        $this->assign('type', $type);

        return $this->viewVars;
    }
    
    
    function testAction()
    {
        $sl = $this->getServiceLocator();
        
        $cp = $sl->get('cPanelApi');
                
        $domain = null;
        $cpUser = $cp->get_user();
        $result = $cp->listparkeddomains($cpUser, $domain);
        
        echo '<pre>';
        print_r($result);
    
        exit;
    }
}