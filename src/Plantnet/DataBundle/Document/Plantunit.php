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
     * @MongoDB\String
     */
    protected $identifier;

    /**
     * @MongoDB\String
     */
    protected $title1;

    /**
     * @MongoDB\String
     */
    protected $title2;

    /**
     * @MongoDB\Hash
     */
    protected $attributes;

    /**
     * @MongoDB\String
     */
    protected $idparent;

    /** @MongoDB\EmbedMany(targetDocument="File") */
    private $files = array();

    /**
     * @MongoDB\ReferenceOne(targetDocument="Plantunit", nullable="true", inversedBy="children")
     * 
     */
    //private $parent;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Plantunit", mappedBy="parent", cascade={"remove"})
     */
    //private $children;

    /**
     * @MongoDB\ReferenceOne(targetDocument="Module", inversedBy="plantunits")
     */
    private $module;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Image", mappedBy="plantunit", cascade={"remove"})
     */
    private $images = array();

    /**
     * @MongoDB\ReferenceMany(targetDocument="Location", mappedBy="plantunit", cascade={"remove"})
     */
    private $locations = array();

    public function __construct()
    {
        $this->files = new \Doctrine\Common\Collections\ArrayCollection();
        //$this->children = new \Doctrine\Common\Collections\ArrayCollection();
        $this->images = new \Doctrine\Common\Collections\ArrayCollection();
        $this->locations = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set id
     *
     * @param id $id
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * Set title1
     *
     * @param string $title1
     */
    public function setTitle1($title1)
    {
        $this->title1 = $title1;
    }

    /**
     * Get title1
     *
     * @return string $title1
     */
    public function getTitle1()
    {
        return $this->title1;
    }

    /**
     * Set title2
     *
     * @param string $title2
     */
    public function setTitle2($title2)
    {
        $this->title2 = $title2;
    }

    /**
     * Get title2
     *
     * @return string $title2
     */
    public function getTitle2()
    {
        return $this->title2;
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
     * Set parent
     *
     * @param Plantnet\DataBundle\Document\Plantunit $parent
     */
    // public function setParent(\Plantnet\DataBundle\Document\Plantunit $parent)
    // {
    //     $this->parent = $parent;
    // }

    /**
     * Get parent
     *
     * @return Plantnet\DataBundle\Document\Plantunit $parent
     */
    // public function getParent()
    // {
    //     return $this->parent;
    // }

    /**
     * Add children
     *
     * @param Plantnet\DataBundle\Document\Plantunit $children
     */
    // public function addChildren(\Plantnet\DataBundle\Document\Plantunit $children)
    // {
    //     $this->children[] = $children;
    // }

    /**
     * Get children
     *
     * @return Doctrine\Common\Collections\Collection $children
     */
    // public function getChildren()
    // {
    //     return $this->children;
    // }

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

    /**
     * Add files
     *
     * @param Plantnet\DataBundle\Document\File $files
     */
    public function addFile(\Plantnet\DataBundle\Document\File $files)
    {
        $this->files[] = $files;
    }

    /**
    * Remove files
    *
    * @param <variableType$files
    */
    public function removeFile(\Plantnet\DataBundle\Document\File $files)
    {
        $this->files->removeElement($files);
    }

    /**
     * Add images
     *
     * @param Plantnet\DataBundle\Document\Image $images
     */
    public function addImage(\Plantnet\DataBundle\Document\Image $images)
    {
        $this->images[] = $images;
    }

    /**
    * Remove images
    *
    * @param <variableType$images
    */
    public function removeImage(\Plantnet\DataBundle\Document\Image $images)
    {
        $this->images->removeElement($images);
    }

    /**
     * Add locations
     *
     * @param Plantnet\DataBundle\Document\Location $locations
     */
    public function addLocation(\Plantnet\DataBundle\Document\Location $locations)
    {
        $this->locations[] = $locations;
    }

    /**
    * Remove locations
    *
    * @param <variableType$locations
    */
    public function removeLocation(\Plantnet\DataBundle\Document\Location $locations)
    {
        $this->locations->removeElement($locations);
    }
}
