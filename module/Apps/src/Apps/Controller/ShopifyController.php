<?php

namespace Apps\Controller;

use Zend, Com, App, Zend\View\Model\JsonModel;


class ShopifyController extends Com\Controller\AbstractController
{


    function apiAction()
    {
        $request = $this->getRequest();
        
        // check if the suer wants to install the application
        if($this->_isInstall())
        {
            // lets check if the request comes from shopify
            $verified = $this->_verifyWebhook($_GET);
            if(! $verified)
            {
                return $this->_badRequest();
            }
            
            //
            
            $sl = $this->getServiceLocator();
            $shop = $_GET['shop'];
            $request = $this->getRequest();
            $uri = $request->getUri();
            
            $dbShopifyApp = $sl->get('App\Db\ShopifyAuth');
            
            $row = $dbShopifyApp->findByStore($shop);
            if(! $row)
            {
                if($request->isPost())
                {
                    $clear = function ($fieldName, Com\Communicator $com) use($request)
                    {
                        $value = $request->getPost($fieldName);
                        
                        $trim = new Zend\Filter\StringTrim();
                        $value = $trim->filter($value);
                        
                        if(empty($value))
                        {
                            $com->addError('Please provide all required values');
                        }
                        
                        return $value;
                    };
                    
                    $com = $this->getCommunicator();
                    
                    $instance = $clear('instance', $com);
                    $username = $clear('username', $com);
                    $password = $clear('password', $com);
                    
                    if($com->isSuccess())
                    {
                        $website = new Com\Validator\Website();
                        if($website->isValid($instance))
                        {
                            if('/' == substr($instance, - 1, 1))
                            {
                                $instance = substr($instance, 0, - 1);
                            }
                            
                            $client = new Zend\Http\Client();
                            $client->setUri("$instance/login/token.php");
                            $client->setMethod('GET');
                            
                            $client->setParameterGet(array(
                                'username' => $username,
                                'password' => $password,
                                'service' => 'lms' 
                            ));
                            
                            try
                            {
                                $response = $client->send();
                                if($response->isOk())
                                {
                                    $body = $response->getBody();
                                    $decoded = Zend\Json\Decoder::decode($body);
                                    if(isset($decoded->error))
                                    {
                                        $com->addError($decoded->error);
                                    }
                                    elseif(! isset($decoded->token))
                                    {
                                        $com->addError("(Token) There was an unexpected error, please try again.", 'instance');
                                    }
                                    else
                                    {
                                        $data = array(
                                            'store' => $shop,
                                            'lms_instance' => $instance,
                                            'lms_token' => $decoded->token 
                                        );
                                        
                                        $dbShopifyApp->doInsert($data);
                                        
                                        // redirect
                                        $queryParams = array(
                                            'hmac' => $_GET['hmac'],
                                            'shop' => $shop,
                                            'signature' => $_GET['signature'],
                                            'timestamp' => $_GET['timestamp'] 
                                        );
                                        $query = http_build_query($queryParams, null, '&');
                                        
                                        $routeParams = array(
                                            'controller' => 'shopify',
                                            'action' => 'api' 
                                        );
                                        
                                        $url = $this->url()->fromRoute('apps', $routeParams) . "?$query";
                                        $this->redirect()->toUrl($url);
                                    }
                                }
                                else
                                {
                                    $com->addError("({$response->getStatusCode()}) There was an unexpected error, please try again.", 'instance');
                                }
                            }
                            catch(\Exception $e)
                            {
                                $com->addError($e->getMessage(), 'instance');
                            }
                        }
                        else
                        {
                            $com->addError('Please provide a valid instance url', 'instance');
                        }
                    }
                }
                
                $this->assign($request->getPost());
                // lets show a small form so the user can provide the instance url
                // and login information
                $this->assign(array(
                    'hmac' => $_GET['hmac'],
                    'shop' => $_GET['shop'],
                    'signature' => $_GET['signature'],
                    'timestamp' => $_GET['timestamp'] 
                ));
                
                return $this->viewVars;
            }
            
            $routeParams = array(
                'controller' => 'shopify',
                'action' => 'confirm' 
            );
            
            $routeOptions = array(
                'force_canonical' => true 
            );
            
            $apiKey = $sl->get('shopify_api');
            $scopes = 'read_products,write_products,read_customers,write_customers,read_orders,write_orders';
            $redirectUri = urlencode($this->url()->fromRoute('apps', $routeParams, $routeOptions));
            
            $url = "https://{$shop}/admin/oauth/authorize?client_id=$apiKey&scope=$scopes&redirect_uri=$redirectUri";
            $this->redirect()->toUrl($url);
            return;
        }
        else
        {
            // bad request
            return $this->_badRequest();
        }
    }


    function confirmAction()
    {
        if($this->_isConfirmingInstallation())
        {
            $verified = $this->_verifyWebhook($_GET);
            if(! $verified)
            {
                return $this->_badRequest();
            }
            
            $sl = $this->getServiceLocator();
            $shop = $_GET['shop'];
            
            $dbShopifyApp = $sl->get('App\Db\ShopifyAuth');
            $row = $dbShopifyApp->findByStore($shop);
            if(! $row)
            {
                return $this->_badRequest();
            }
            
            $uri = "https://$shop/admin/oauth/access_token";
            $apiKey = $sl->get('shopify_api');
            $shopifyAppSecret = $sl->get('shopify_secret');
            
            $paramPost = array(
                'client_id' => $apiKey,
                'client_secret' => $shopifyAppSecret,
                'code' => $_GET['code'] 
            );
            
            try
            {
                $adapter = new Zend\Http\Client\Adapter\Curl();
                
                $client = new Zend\Http\Client($uri);
                $client->setAdapter($adapter);
                $client->setParameterGet($paramPost);
                $client->setMethod('POST');
                
                $response = $client->send();
                
                if($response->isSuccess())
                {
                    $re = Zend\Json\Decoder::decode($response->getBody());
                    if(! $re->access_token)
                    {
                        return $this->_badRequest();
                    }
                    
                    // shopify client
                    $shopifyClient = new App\Model\Shopify\Client($shop, $re->access_token);
                    
                    $routeOptions = array(
                        'force_canonical' => true 
                    );
                    
                    // register the uninstall webhook
                    $webHookUrl = $this->url()->fromRoute('apps', array(
                        'controller' => 'shopify',
                        'action' => 'unistall' 
                    ), $routeOptions);
                    
                    $shopifyClient->call('POST', '/admin/webhooks.json', array(
                        'webhook' => array(
                            'address' => $webHookUrl,
                            'format' => 'json',
                            'topic' => 'app/uninstalled' 
                        ) 
                    ));
                    
                    // register the orders/paid webhook
                    $webHookUrl = $this->url()->fromRoute('apps', array(
                        'controller' => 'shopify',
                        'action' => 'create-user' 
                    ), $routeOptions);
                    
                    $shopifyClient->call('POST', '/admin/webhooks.json', array(
                        'webhook' => array(
                            'address' => $webHookUrl,
                            'format' => 'json',
                            'topic' => 'orders/paid' 
                        ) 
                    ));
                    
                    // save in database the access token
                    $row->code = $_GET['code'];
                    $row->access_token = $re->access_token;
                    
                    $data = $row->toArray();
                    $where = array(
                        'id = ?' => $row->id 
                    );
                    
                    $dbShopifyApp->doUpdate($data, $where);
                    
                    $uri = "https://$shop/admin/apps";
                    $this->redirect()->toUrl($uri);
                    return $this->getResponse();
                }
                else
                {
                    // there was an error, redirect the suer to the paradiso app on shopify store
                    $uri = "https://apps.shopify.com/paradiso-lms/";
                    $this->redirect()->toUrl($uri);
                    
                    return $this->getResponse();
                }
            }
            catch(\Exception $e)
            {
                dd($e);
            }
        }
        else
        {
            return $this->_badRequest();
        }
    }


    function unistallAction()
    {
        $date = date('Y.m.d H:i:s');
        $get = print_r($_GET, 1);
        $post = print_r($_POST, 1);
        $allheaders = print_r(getallheaders(), 1);
        
        $message = "
        -----------------------------------------------
        UNISTALL
        _DATE $date
        _GET $get
        _POST $post
        _ALLHEADERS $allheaders
        -----------------------------------------------
        ";
        
        $sl = $this->getServiceLocator();
        $log = $sl->get('Zend\Log\Logger');
        $log->debug($message);
        
        exit();
    }


    function createUserAction()
    {
        $date = date('Y.m.d H:i:s');
        $get = print_r($_GET, 1);
        $post = print_r($_POST, 1);
        $allheaders = print_r(getallheaders(), 1);
        
        $message = "
        -----------------------------------------------
        CREATE-USER
        _DATE $date
        _GET $get
        _POST $post
        _ALLHEADERS $allheaders
        -----------------------------------------------
        ";
        
        $sl = $this->getServiceLocator();
        $log = $sl->get('Zend\Log\Logger');
        $log->debug($message);
        
        exit();
    }


    function prefAction()
    {
        echo 21;
        exit();
    }


    function supportAction()
    {
        echo 22;
        exit();
    }


    /**
     *
     * @return \Zend\View\Model\JsonModel
     */
    protected function _badRequest()
    {
        // bad request
        $response = $this->getResponse();
        $response->setStatusCode(\Zend\Http\Response::STATUS_CODE_404);
        
        return $response;
    }


    /**
     * Returns a value indicating if the request is for installing the application
     *
     * @return boolean
     */
    protected function _isInstall()
    {
        $flag = isset($_GET['hmac']);
        $flag = ($flag && isset($_GET['shop']));
        $flag = ($flag && isset($_GET['signature']));
        $flag = ($flag && isset($_GET['timestamp']));
        
        return $flag;
    }


    /**
     * Returns a value indicating if the request is for installing the application
     *
     * @return boolean
     */
    protected function _isConfirmingInstallation()
    {
        $flag = isset($_GET['code']);
        $flag = ($flag && isset($_GET['hmac']));
        $flag = ($flag && isset($_GET['shop']));
        $flag = ($flag && isset($_GET['signature']));
        $flag = ($flag && isset($_GET['timestamp']));
        
        return $flag;
    }


    /**
     * Verify if is a valid request
     *
     * For more information go to: https://docs.shopify.com/api/webhooks/using-webhooks
     * and look for "Verify a webhook created through the API"
     *
     * @param array $params
     * @var string hmac
     * @var string shop
     * @var int timestamp
     *
     * @return boolean
     */
    protected function _verifyWebhook(array $params)
    {
        if(! isset($params['timestamp']))
            return false;
        
        $seconds = 24 * 60 * 60; // seconds in a day
        $olderThan = $params['timestamp'] < (time() - $seconds);
        
        if($olderThan)
            return false;
        
        $p = array();
        foreach($params as $param => $value)
        {
            if($param != 'signature' && $param != 'hmac')
            {
                $p[$param] = "{$param}={$value}";
            }
        }
        
        $sl = $this->getServiceLocator();
        $shopifyAppSecret = $sl->get('shopify_secret');
        
        asort($p);
        
        $p = implode('&', $p);
        $hmac = $params['hmac'];
        $calculatedHmac = hash_hmac('sha256', $p, $shopifyAppSecret);
        
        return ($hmac == $calculatedHmac);
    }
}