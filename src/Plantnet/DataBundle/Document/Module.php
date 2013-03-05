<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\ModuleRepository")
 */
class Module
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\ReferenceOne(targetDocument="Collection", inversedBy="modules")
     */
    private $collection;

    /**
     * @MongoDB\String
     */
    protected $name;

    /**
     * @MongoDB\String
     */
    private $type;

    /**
     * @MongoDB\ReferenceOne(targetDocument="File")
     * @Assert\File(maxSize = "30M", mimeTypes = {
     *   "text/plain"
     * })
     */
    protected $attachment;

    /**
     * @MongoDB\EmbedMany(targetDocument="Property")
     */
    protected $properties;

    /**
     * @MongoDB\ReferenceOne(targetDocument="Module", nullable="true")
     */
    private $parent;

    /**
     * 
     * @Assert\File(maxSize = "30M")
     */
    protected $file;

    public function __construct()
    {
        $this->properties = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Get id
     *
     * @return id $id
     */
    public function getId()
    {
        return $this->id;
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
     * Get name
     *
     * @return string $name
     */
    public function getName_fname()
    {
        $filename=mb_strtolower($this->name,'UTF-8');
        $filename=eregi_replace("[ ]+",'-',strtolower($filename));
        $filename=preg_replace('/([^.a-z0-9]+)/i','_',$filename);
        return $filename;
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
     * Set parent
     *
     * @param Plantnet\DataBundle\Document\Module $parent
     */
    public function setParent(\Plantnet\DataBundle\Document\Module $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Get parent
     *
     * @return Plantnet\DataBundle\Document\Module $parent
     */
    public function getParent()
    {
        return $this->parent;
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
     * Set attachment
     *
     * @param Plantnet\DataBundle\Document\File $attachment
     */
    public function setAttachment(\Plantnet\DataBundle\Document\File $attachment)
    {
        $this->attachment = $attachment;
    }

    /**
     * Get attachment
     *
     * @return Plantnet\DataBundle\Document\File $attachment
     */
    public function getAttachment()
    {
        return $this->attachment;
    }
}