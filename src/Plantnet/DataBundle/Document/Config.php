<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\ConfigRepository")
 */
class Config
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\String
     */
    protected $defaultlanguage;

    /**
     * @MongoDB\Hash
     */
    protected $availablelanguages;

    /**
     * To String
     *
     * @return string
     */
    public function __toString()
    {
        return 'Config';
    }

    public function __construct()
    {
        $this->availablelanguages = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
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
     * Set availablelanguages
     *
     * @param hash $availablelanguages
     */
    public function setAvailablelanguages($availablelanguages)
    {
        $this->availablelanguages = $availablelanguages;
    }

    /**
     * Get availablelanguages
     *
     * @return hash $availablelanguages
     */
    public function getAvailablelanguages()
    {
        return $this->availablelanguages;
    }
}
