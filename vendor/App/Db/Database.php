<?php

namespace App\Db;

use Com, Zend;

class Database extends Com\Db\AbstractDb
{
    /**
     *
     * @var string
     */
    protected $tableName = 'database';
    
    
    
    /**
    *
    * @return int
    */
    function countFree()
    {
        $sl = $this->getServiceLocator();
       
        $dbDatabase = $this;
        $dbClientHasDb = $sl->get('App\Db\Client\HasDatabase');

        //
        $select = new Zend\Db\Sql\Select();

        // tabla 
        $select->from(array(
            'd' => $dbDatabase->getTable()
        ));

        $count = new Zend\Db\Sql\Literal('COUNT(*) AS c');
        $select->columns(array($count));

        // join 
        $select->join(array('chd' => $dbClientHasDb->getTable()), 'chd.database_id = d.id', array());

        //
        $predicate = new Zend\Db\Sql\Predicate\Literal('chd.client_id IS NULL');
        $select->where($predicate);
        
        //
        return $this->executeCustomSelect($select)->current()->c;
    }
    
    
    /**
    *
    * @return Com\Entity\Record | null
    */
    function findFreeDatabase()
    {
        $sl = $this->getServiceLocator();
       
        $dbDatabase = $this;
        $dbClientHasDb = $sl->get('App\Db\Client\HasDatabase');

        //
        $select = new Zend\Db\Sql\Select();

        // tabla 
        $select->from(array(
            'd' => $dbDatabase->getTable()
        ));

        // join 
        $select->join(array('chd' => $dbClientHasDb->getTable()), 'chd.database_id = d.id', array(), 'LEFT');

        //
        $predicate = new Zend\Db\Sql\Predicate\Literal('chd.client_id IS NULL');
        $select->where($predicate);
        $select->order('d.id ASC');
        
        //
        $select->limit(1);
        
        //
        return $this->executeCustomSelect($select)->current();
    }
    
    
    function findDatabaseByClientId($clientId)
    {
        $sl = $this->getServiceLocator();
       
        $dbDatabase = $this;
        $dbClientHasDb = $sl->get('App\Db\Client\HasDatabase');

        //
        $select = new Zend\Db\Sql\Select();

        // tabla 
        $select->from(array(
            'd' => $dbDatabase->getTable()
        ));

        // join 
        $select->join(array('chd' => $dbClientHasDb->getTable()), 'chd.database_id = d.id', array());

        //
        $where = array();
        $where['chd.client_id = ?'] = $clientId;
        $select->where($where);
        
        //
        return $this->executeCustomSelect($select);
    }
}