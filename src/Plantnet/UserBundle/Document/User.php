<?php

namespace Plantnet\UserBundle\Document;

use FOS\UserBundle\Document\User as BaseUser;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique as MongoDBUnique;

/**
 * @MongoDB\Document
 * @MongoDBUnique(fields="dbName", groups={"Registration"})
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
    protected $dbName;

    public function __construct()
    {
        parent::__construct();
        // your own logic
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
}