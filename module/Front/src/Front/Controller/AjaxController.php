<?php
namespace Front\Controller;

use Zend, Com, Zend\View\Model\JsonModel;
 

class AjaxController extends Com\Controller\AbstractController
{

   
    function checkInstanceCreatedAction()
    {
        $request = $this->getRequest();
        
        $com = $this->getCommunicator();
        $com->setNoSuccess();
      
        if($request->isPost())
        {
            $uri = $request->getPost('website');
            
            $client = new Zend\Http\Client();
            $client->setUri($uri);
            
            try
            {
                $response = $client->send();
                
                if($response->isOk())
                {
                    // analizamos el cuerpo de la pagina para buscar si esta intentando redireccionar
                    // a la pagina por defecto de cpanel
                    $body = $response->getBody();
                    $pos = stripos($body, '/cgi-sys/defaultwebpage.cgi');
                    if(false === $pos)
                    {
                        $com->setSuccess();
                    }
                }
            }
            catch(\Exception $e)
            {
                ;
            }
        }
        
        $result = new JsonModel($com->toArray());

        return $result;
    }
    
    
    function canEditInstanceAction()
    {
        $request = $this->getRequest();
        
        $com = $this->getCommunicator();
        $com->setNoSuccess();
      
        if($request->isPost())
        {
            $email = $request->getPost('email');
            
            $sl = $this->getServiceLocator();
            
            $mInstance = $sl->get('App\Model\Freemium\Instance');
            if($mInstance->canEditInstanceName($email))
            {
                $com->setSuccess();
            }
        }
        
        $result = new JsonModel($com->toArray());

        return $result;
    }
}