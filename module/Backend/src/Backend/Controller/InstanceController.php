<?php

namespace Backend\Controller;

use Zend, Com, App;


class InstanceController extends Com\Controller\BackendController
{

    function listAction()
    {
        $sl = $this->getServiceLocator();
        
        $this->loadCommunicator();

        $this->assign('grid_title', "Instances");

        $grid = new App\DataGrid\Client($sl, $this->viewVars);
        $view = $grid->render();                
        
        $colorBoxView = new Zend\View\Model\ViewModel();
        $colorBoxView->setTemplate('backend/instance/list.phtml');
        
        $view->addChild($colorBoxView, 'after_title');
        
        return $view;
    }
    
    
    
    function deleteAction()
    {
        $sl = $this->getServiceLocator();
            
        try
        {
            $domain = $this->_params('domain', '');
            
            $config = $sl->get('config');
            
            $dbClient = $sl->get('App\Db\Client');
            $dbDatabase = $sl->get('App\Db\Database');
            $dbClientDatabase = $sl->get('App\Db\Client\HasDatabase');
            
            $topDomain = $config['freemium']['top_domain'];
            $mDataPath = $config['freemium']['path']['mdata'];
            $masterSqlFile = $config['freemium']['path']['master_sql_file'];
            $configPath = $config['freemium']['path']['config'];
            
            $cpanelUser = $config['freemium']['cpanel']['username'];
            $cpanelPass = $config['freemium']['cpanel']['password'];
            
            $dbPrefix = $config['freemium']['db']['prefix'];
            $dbUser = $config['freemium']['db']['user'];
            $dbHost = $config['freemium']['db']['host'];
            $dbPassword = $config['freemium']['db']['password'];

            
            //
            $rowsetClient = $dbClient->findByDomain($domain);
            $countClient = $rowsetClient->count();
            
            if(0 == $countClient)
            {
                $errorMessage = "$countClient records found with the domain name $domain.";
                return $this->_redirectToListWithMessage($errorMessage, true);
            }
            
            foreach($rowsetClient as $rowClient)
            {
                $clientId = $rowClient->id;
                
                //
                $rowsetDatabase = $dbDatabase->findDatabaseByClientId($clientId);
                $countDatabases = $rowsetDatabase->count();
                if($countDatabases > 1)
                {
                    $errorMessage = "Ups, $count databases found related to the domain name $domain, please have a look first.";
                    return $this->_redirectToListWithMessage($errorMessage, true);
                }
                elseif(1 == $countDatabases)
                {
                    $rowDatabase = $rowsetDatabase->current();
                    $dbName = $rowDatabase->db_name;
                    $dbNameNoPrefix = str_replace($dbPrefix, '', $dbName);
                    
                    $cp = $sl->get('cPanelApi');

                    /*************************************/
                    // delete the database
                    /*************************************/
                    $response = $cp->api2_query($cpanelUser, 'MysqlFE', 'deletedb', array(
                        'db' => $dbName,
                    ));
                    
                    if(isset($response['error']) || isset($response['event']['error']))
                    {
                        $errorMessage = isset($response['error']) ? $response['error'] : $response['event']['error'];
                        return $this->_redirectToListWithMessage($errorMessage, true);
                    }
                    
                    // delete database from database database ;-)
                    $where = array();
                    $where['id = ?'] = $rowDatabase->id;
                    $dbDatabase->doDelete($where);
                    
                    //
                    $where = array();
                    $where['database_id = ?'] = $rowDatabase->id;
                    $dbClientDatabase->doDelete($where);
                }
                
                // update client email and domain 
                $where = array();
                $where['id = ?'] = $rowClient->id;
                
                $uid = uniqid();
                $data = array(
                    'email' => "{$rowClient->email}.$uid"
                    ,'domain' => "{$rowClient->domain}.$uid"
                );
                $dbClient->doUpdate($data, $where);
            }
            
            /*************************************/
            // delete the domain
            /*************************************/
            $response = $cp->unpark($cpanelUser, $domain);
                
            if(isset($response['error']) || isset($response['event']['error']))
            {
                $errorMessage = isset($response['error']) ? $response['error'] : $response['event']['error'];
                return $this->_redirectToListWithMessage($errorMessage, true);
            }
            
            
            /*************************************/
            // delete mdata folder
            /*************************************/
            "rm {$mDataPath}/$domain/ -Rf";
            exec("rm {$mDataPath}/$domain/ -Rf");
            
            
            /*************************************/
            // delete config file
            /*************************************/
            $configFilename = "{$configPath}/{$domain}.php";
            exec("rm $configFilename");
            
            $message = "Domain $domain successfull removed.";
            return $this->_redirectToListWithMessage($message, false);
        }
        catch (\Exception $e)
        {
            $errorMessage = $e->getMessage();
            return $this->_redirectToListWithMessage($errorMessage, true);
        }
    }
    
    
    protected function _redirectToListWithMessage($message, $isError)
    {
        $com = $this->getCommunicator();
        
        if($isError)
        {
            $com->addError($message);
        }
        else
        {
            $com->setSuccess($message);
        }
        
        $this->saveCommunicator($com);
        
        return $this->redirect()->toRoute('backend/wildcard', array(
            'controller' => 'instance'
            ,'action' => 'list'
        ));
    }
    
    
    function infoAction()
    {
        $this->layout('layout/blank');
        
        // client id
        $id = $this->_params('id', 0);
        $sl = $this->getServiceLocator();
        
        $dbClient = $sl->get('App\Db\Client');
        $dbDatabase = $sl->get('App\Db\Database');
        
        $rowClient = $dbClient->findByPrimaryKey($id);
        $result = $dbDatabase->findDatabaseByClientId($id);
        
        if($rowClient && $result->count())
        {
            $rowDb = $result->current();
            
            $client = new App\Lms\Services\Client();
            $client->setServicesToken($rowDb->db_name);
            
            $client->setServerUri("http://{$rowClient->domain}/services/index.php");
            $response = $client->request('count_users');
            
            if($response->isError())
            {
                $this->getCommunicator()->addError($response->getMessage());
            }
            else
            {
                $this->assign($response->getParams());
            }
        }
        else
        {
            if(!$rowClient)
            {
                $this->getCommunicator()->addError('Client not found');
            }
            else
            {
                $this->getCommunicator()->addError('The client do not have an assigned database.');
            }
        }
        
        
        return $this->viewVars;
    }
}