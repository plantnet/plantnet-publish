<?php

/**
 * This file is part of the Identify package.
 *
 * (c) Julien Barbe <julien.barbe@me.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Plantnet\UserBundle\Document;

use FOS\UserBundle\Document\User as BaseUser;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
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
    private $dbName;

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