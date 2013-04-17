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
     * @MongoDB\String
     */
    protected $name;

    /**
     * @MongoDB\String
     */
    protected $description;

    /**
     * @MongoDB\String
     */
    private $type;

    /**
     * @MongoDB\String
     */
    private $uploaddir;
    
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
     * 
     * @Assert\File(maxSize = "30M")
     */
    protected $file;

    /**
     * @MongoDB\ReferenceOne(targetDocument="Collection", inversedBy="modules")
     */
    private $collection;

    /**
     * @MongoDB\ReferenceOne(targetDocument="Module", nullable="true", inversedBy="children")
     */
    private $parent;

    /**
     * @MongoDB\ReferenceMany(
     *      targetDocument="Module",
     *      sort={"name"="asc"},
     *      nullable="true",
     *      mappedBy="parent",
     *      cascade={"remove"}
     *  )
     */
    private $children;

    /**
     * @MongoDB\Boolean
     */
    private $updating;

    /**
     * @MongoDB\Boolean
     */
    private $deleting;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Plantunit", mappedBy="module", cascade={"remove"})
     */
    private $plantunits = array();

    /**
     * @MongoDB\ReferenceMany(targetDocument="Image", mappedBy="module", cascade={"remove"})
     */
    private $images = array();

    /**
     * @MongoDB\ReferenceMany(targetDocument="Location", mappedBy="module", cascade={"remove"})
     */
    private $locations = array();

    /**
     * @MongoDB\ReferenceMany(targetDocument="Other", mappedBy="module", cascade={"remove"})
     */
    private $others = array();

    /**
     * @MongoDB\Int
     */
    private $nbrows;

    /**
     * To String
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

    public function __construct()
    {
        $this->properties = new \Doctrine\Common\Collections\ArrayCollection();
        $this->children = new \Doctrine\Common\Collections\ArrayCollection();
        $this->plantunits = new \Doctrine\Common\Collections\ArrayCollection();
        $this->others = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get description
     *
     * @return string $description
     */
    public function getDescription()
    {
        return $this->description;
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
     * Add children
     *
     * @param Plantnet\DataBundle\Document\Module $children
     */
    public function addChildren(\Plantnet\DataBundle\Document\Module $children)
    {
        $this->children[] = $children;
    }

    /**
     * Get children
     *
     * @return Doctrine\Common\Collections\Collection $children
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set updating
     *
     * @param boolean $updating
     */
    public function setUpdating($updating)
    {
        $this->updating = $updating;
    }

    /**
     * Get updating
     *
     * @return boolean $updating
     */
    public function getUpdating()
    {
        return $this->updating;
    }

    /**
     * Set deleting
     *
     * @param boolean $deleting
     */
    public function setDeleting($deleting)
    {
        $this->deleting = $deleting;
    }

    /**
     * Get deleting
     *
     * @return boolean $deleting
     */
    public function getDeleting()
    {
        return $this->deleting;
    }

    /**
     * Add plantunits
     *
     * @param Plantnet\DataBundle\Document\Plantunit $plantunits
     */
    public function addPlantunits(\Plantnet\DataBundle\Document\Plantunit $plantunits)
    {
        $this->plantunits[] = $plantunits;
    }

    /**
     * Get plantunits
     *
     * @return Doctrine\Common\Collections\Collection $plantunits
     */
    public function getPlantunits()
    {
        return $this->plantunits;
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
     * Add others
     *
     * @param Plantnet\DataBundle\Document\Other $others
     */
    public function addOthers(\Plantnet\DataBundle\Document\Other $others)
    {
        $this->others[] = $others;
    }

    /**
     * Get others
     *
     * @return Doctrine\Common\Collections\Collection $others
     */
    public function getOthers()
    {
        return $this->others;
    }

    /**
     * Set nbrows
     *
     * @param int $nbrows
     */
    public function setNbrows($nbrows)
    {
        $this->nbrows = $nbrows;
    }

    /**
     * Get nbrows
     *
     * @return int $nbrows
     */
    public function getNbrows()
    {
        return $this->nbrows;
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
    * Remove children
    *
    * @param <variableType$children
    */
    public function removeChildren(\Plantnet\DataBundle\Document\Module $children)
    {
        $this->children->removeElement($children);
    }

    /**
     * Add plantunits
     *
     * @param Plantnet\DataBundle\Document\Plantunit $plantunits
     */
    public function addPlantunit(\Plantnet\DataBundle\Document\Plantunit $plantunits)
    {
        $this->plantunits[] = $plantunits;
    }

    /**
    * Remove plantunits
    *
    * @param <variableType$plantunits
    */
    public function removePlantunit(\Plantnet\DataBundle\Document\Plantunit $plantunits)
    {
        $this->plantunits->removeElement($plantunits);
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
