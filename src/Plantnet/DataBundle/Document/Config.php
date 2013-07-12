<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

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
     * 
     * @Assert\Image(maxSize = "500k")
     */
    protected $file;

    /**
     * @MongoDB\String
     */
    protected $filepath;

    /**
     * @MongoDB\Boolean
     */
    private $islocked;

    /**
     * @MongoDB\String
     */
    protected $originaldb;

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

    /**
     * Set File
     *
     * @param text $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * Get File
     *
     * @return text $file
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Set filepath
     *
     * @param string $filepath
     */
    public function setFilepath($filepath)
    {
        $this->filepath = $filepath;
    }

    /**
     * Get filepath
     *
     * @return string $filepath
     */
    public function getFilepath()
    {
        return $this->filepath;
    }

    /**
     * Set islocked
     *
     * @param boolean $islocked
     */
    public function setIslocked($islocked)
    {
        $this->islocked = $islocked;
    }

    /**
     * Get islocked
     *
     * @return boolean $islocked
     */
    public function getIslocked()
    {
        return $this->islocked;
    }

    /**
     * Set originaldb
     *
     * @param string $originaldb
     */
    public function setOriginaldb($originaldb)
    {
        $this->originaldb = $originaldb;
    }

    /**
     * Get originaldb
     *
     * @return string $originaldb
     */
    public function getOriginaldb()
    {
        return $this->originaldb;
    }
}
