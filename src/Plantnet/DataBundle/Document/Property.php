<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @MongoDB\EmbeddedDocument
 */
class Property
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\String
     */
    protected $name;

    /**
     * @MongoDB\String
     */
    protected $type;

    /**
     * @MongoDB\Boolean
     */
    private $main;

    /**
     * @MongoDB\Boolean
     */
    private $details;

    /**
     * Get id
     *
     * @return id $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * To String
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
    
    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get name
     *
     * @return string $name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set main
     *
     * @param boolean $main
     */
    public function setMain($main)
    {
        $this->main = $main;
    }

    /**
     * Get main
     *
     * @return boolean $main
     */
    public function getMain()
    {
        return $this->main;
    }

    /**
     * Set details
     *
     * @param boolean $details
     */
    public function setDetails($details)
    {
        $this->details = $details;
    }

    /**
     * Get details
     *
     * @return boolean $details
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Set type
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return string $type
     */
    public function getType()
    {
        return $this->type;
    }
}
