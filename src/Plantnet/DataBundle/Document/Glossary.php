<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\GlossaryRepository")
 */
class Glossary
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\String
     */
    private $uploaddir;

    /**
     * 
     * @Assert\File(maxSize = "60M")
     */
    protected $file;

    /**
     * @MongoDB\EmbedMany(targetDocument="Property")
     */
    protected $properties;

    /**
     * @MongoDB\ReferenceMany(
     *      targetDocument="Definition",
     *      sort={"name"="asc"},
     *      mappedBy="glossary",
     *      cascade={"remove"}
     *  )
     */
    protected $definitions = array();

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Collection",
     *      inversedBy="glossary"
     *  )
     */
    private $collection;

    public function __construct()
    {
        $this->properties = new \Doctrine\Common\Collections\ArrayCollection();
        $this->definitions = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set uploaddir
     *
     * @param string $uploaddir
     */
    public function setUploaddir($uploaddir)
    {
        $this->uploaddir = $uploaddir;
    }

    /**
     * Get uploaddir
     *
     * @return string $uploaddir
     */
    public function getUploaddir()
    {
        return $this->uploaddir;
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
     * Add properties
     *
     * @param Plantnet\DataBundle\Document\Property $properties
     */
    public function addPropertie(\Plantnet\DataBundle\Document\Property $properties)
    {
        $this->properties[] = $properties;
    }

    /**
    * Remove properties
    *
    * @param <variableType$properties
    */
    public function removePropertie(\Plantnet\DataBundle\Document\Property $properties)
    {
        $this->properties->removeElement($properties);
    }

    /**
     * Add properties
     *
     * @param Plantnet\DataBundle\Document\Property $properties
     */
    public function addProperties(\Plantnet\DataBundle\Document\Property $properties)
    {
        $this->properties[] = $properties;
    }

    /**
     * Get properties
     *
     * @return Doctrine\Common\Collections\Collection $properties
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Add definitions
     *
     * @param Plantnet\DataBundle\Document\Definition $definitions
     */
    public function addDefinition(\Plantnet\DataBundle\Document\Definition $definitions)
    {
        $this->definitions[] = $definitions;
    }

    /**
    * Remove definitions
    *
    * @param <variableType$definitions
    */
    public function removeDefinition(\Plantnet\DataBundle\Document\Definition $definitions)
    {
        $this->definitions->removeElement($definitions);
    }

    /**
     * Add definitions
     *
     * @param Plantnet\DataBundle\Document\Definition $definitions
     */
    public function addDefinitions(\Plantnet\DataBundle\Document\Definition $definitions)
    {
        $this->definitions[] = $definitions;
    }

    /**
     * Get definitions
     *
     * @return Doctrine\Common\Collections\Collection $definitions
     */
    public function getDefinitions()
    {
        return $this->definitions;
    }

    /**
     * Set collection
     *
     * @param Plantnet\DataBundle\Document\Collection $collection
     */
    public function setCollection(\Plantnet\DataBundle\Document\Collection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * Get collection
     *
     * @return Plantnet\DataBundle\Document\Collection $collection
     */
    public function getCollection()
    {
        return $this->collection;
    }
}
