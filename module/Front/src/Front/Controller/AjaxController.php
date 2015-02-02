<?php
namespace Front\Controller;

use Zend, Com, Zend\View\Model\JsonModel;
 

class AjaxController extends Com\Controller\AbstractController
{

   
    function checkWebsiteAction()
    {
        $request = $this->getRequest();
      
        if($request->isPost())
        {
            $uri = $request->getPost('website');
            #$uri = 'http://gilson2.paradisolms.com';
            
            $client = new Zend\Http\Client();
            $client->setUri($uri);
            
            $com = $this->getCommunicator();
            
            try
            {
                $response = $client->send();
                if($response->isOk())
                {
                    // analizamos el cuerpo de la pagina para buscar si esta intentando redireccionar
                    // a la pagina por defecto de cpanel
                    $body = $response->getBody();
                    $pos = stripos($body, '/cgi-sys/defaultwebpage.cgi');
                    if($pos === false)
                    {
                        $com->setSuccess();
                    }
                    else
                    {
                        $com->setNoSuccess();
                    }
                }
                else
                {
                    $com->setNoSuccess();
                }
            }
            catch(\Exception $e)
            {
                $com->setNoSuccess();
            }
        }
        
        $result = new JsonModel($com->toArray());

        return $result;
    }
}