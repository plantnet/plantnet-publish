<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\PlantunitRepository")
 */
class Plantunit
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\ReferenceOne(targetDocument="Module")
     */
    private $module;

    /**
     * @MongoDB\Hash
     */
    protected $attributes;

    /**
     * @MongoDB\ReferenceOne(targetDocument="Plantunit")
     * 
     */
    private $parent;

    /**
     * @MongoDB\String
     */
    protected $idparent;
    
    /**
     * @MongoDB\String
     */
    protected $identifier;

    /** @MongoDB\EmbedMany(targetDocument="File") */
    private $files = array();

    /** @MongoDB\EmbedMany(targetDocument="Image") */
    // private $images = array();

    /**
     * @MongoDB\ReferenceMany(targetDocument="Image", cascade={"remove"})
     */
    private $images = array();

    /**
     * @MongoDB\ReferenceMany(targetDocument="Location", cascade={"remove"})
     */
    private $locations = array();

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
     * Set id
     *
     * @param id $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /*public function addAttribute($name, $value, $main, $details)
    {
        //$key = preg_replace('/[^a-z0-9\ \_]/i', '', $name);
        //$key = preg_replace('/\s+/i', '_', $key);
        //$key = strtolower($key);
        $this->attributes[$name] = array('value' =>$value, 'label' => $name, 'main' => $main, 'details'=>$details);
    }

    public function getAttribute($name)
    {
        return $this->attributes[$name];
    }*/

    /**
     * Set module
     *
     * @param Plantnet\DataBundle\Document\Module $module
     */
    public function setModule(\Plantnet\DataBundle\Document\Module $module)
    {
        $this->module = $module;
    }

    /**
     * Get module
     *
     * @return Plantnet\DataBundle\Document\Module $module
     */
    public function getModule()
    {
        return $this->module;
    }
    public function __construct()
    {
        $this->files = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Add files
     *
     * @param Plantnet\DataBundle\Document\File $files
     */
    public function addFiles(\Plantnet\DataBundle\Document\File $files)
    {
        $this->files[] = $files;
    }

    /**
     * Get files
     *
     * @return Doctrine\Common\Collections\Collection $files
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Set attributes
     *
     * @param hash $attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Get attributes
     *
     * @return hash $attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Add images
     *
     * @param Plantnet\DataBundle\Document\Image $images
     */
    public function addImages(\Plantnet\DataBundle\Document\Image $images)
    {
        $this->images[] = $images;
    }

    /**
     * Get images
     *
     * @return Doctrine\Common\Collections\Collection $images
     */
    public function getImages()
    {
        return $this->images;
    }

    /**
     * Set identifier
     *
     * @param string $identifier
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Get identifier
     *
     * @return string $identifier
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Set parent
     *
     * @param Plantnet\DataBundle\Document\Plantunit $parent
     */
    public function setParent(\Plantnet\DataBundle\Document\Plantunit $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Get parent
     *
     * @return Plantnet\DataBundle\Document\Plantunit $parent
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set idparent
     *
     * @param string $idparent
     */
    public function setIdparent($idparent)
    {
        $this->idparent = $idparent;
    }

    /**
     * Get idparent
     *
     * @return string $idparent
     */
    public function getIdparent()
    {
        return $this->idparent;
    }

    /**
     * Add locations
     *
     * @param Plantnet\DataBundle\Document\Location $locations
     */
    public function addLocations(\Plantnet\DataBundle\Document\Location $locations)
    {
        $this->locations[] = $locations;
    }

    /**
     * Get locations
     *
     * @return Doctrine\Common\Collections\Collection $locations
     */
    public function getLocations()
    {
        return $this->locations;
    }
}