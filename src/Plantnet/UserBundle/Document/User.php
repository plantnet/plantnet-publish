<?php

namespace Plantnet\UserBundle\Document;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique as MongoDBUnique;

/**
 * @MongoDB\Document
 * @MongoDBUnique(fields="dbNameUq", groups={"Registration"})
 */
class User extends BaseUser
{
    /**
     * @MongoDB\Id(strategy="auto")
     */
    protected $id;

    /**
     * @MongoDB\String
     */
    protected $dbNameUq;

    /**
     * @MongoDB\String
     */
    protected $dbName;

    /**
     * @MongoDB\Hash
     */
    protected $dbs;

    /**
     * @MongoDB\String
     */
    protected $defaultlanguage;

    /**
     * @MongoDB\Boolean
     */
    private $super;

    public function __construct()
    {
        parent::__construct();
        // your own logic
        $this->dbs = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set dbNameUq
     *
     * @param string $dbNameUq
     */
    public function setDbNameUq($dbNameUq)
    {
        $this->dbNameUq = $dbNameUq;
    }

    /**
     * Get dbNameUq
     *
     * @return string $dbNameUq
     */
    public function getDbNameUq()
    {
        return $this->dbNameUq;
    }

    /**
     * Set dbName
     *
     * @param string $dbName
     */
    public function setDbName($dbName)
    {
        $this->dbName = $dbName;
    }

    /**
     * Get dbName
     *
     * @return string $dbName
     */
    public function getDbName()
    {
        return $this->dbName;
    }

    /**
     * Set dbs
     *
     * @param hash $dbs
     */
    public function setDbs($dbs)
    {
        $this->dbs = $dbs;
    }

    /**
     * Get dbs
     *
     * @return hash $dbs
     */
    public function getDbs()
    {
        return $this->dbs;
    }
    
    /**
     * Set defaultlanguage
     *
     * @param string $defaultlanguage
     */
    public function setDefaultlanguage($defaultlanguage)
    {
        $this->defaultlanguage = $defaultlanguage;
    }

    /**
     * Get defaultlanguage
     *
     * @return string $defaultlanguage
     */
    public function getDefaultlanguage()
    {
        return $this->defaultlanguage;
    }

    /**
     * Set super
     *
     * @param boolean $super
     */
    public function setSuper($super)
    {
        $this->super = $super;
    }

    /**
     * Get super
     *
     * @return boolean $super
     */
    public function getSuper()
    {
        return $this->super;
    }
}