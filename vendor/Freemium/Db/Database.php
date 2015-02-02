<?php

namespace Freemium\Db;

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
       $predicate = new Zend\Db\Sql\Predicate\Literal('client_id IS NULL');
       return $this->count($predicate);
    }
    
    /**
    *
    * @return Com\Entity\Record | null
    */
    function findFreeDatabase()
    {
       $predicate = new Zend\Db\Sql\Predicate\Literal('client_id IS NULL');
       return $this->findBy($predicate, array(), null, 1)->current();
    }
}