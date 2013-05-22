<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\TaxonRepository")
 */
class Taxon
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
     * @MongoDB\Int
     */
    private $level;

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Taxon",
     *      nullable="true",
     *      inversedBy="children"
     *  )
     */
    private $parent;

    /**
     * @MongoDB\ReferenceMany(
     *      targetDocument="Taxon",
     *      sort={"name"="asc"},
     *      nullable="true",
     *      mappedBy="parent",
     *      cascade={"remove"}
     *  )
     */
    private $children;

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Module",
     *      inversedBy="taxons"
     *  )
     */
    private $module;

    /**
     * @MongoDB\ReferenceMany(
     *      targetDocument="Plantunit",
     *      mappedBy="taxon"
     *  )
     */
    private $plantunits = array();

    /**
     * @MongoDB\Int
     */
    private $nbpunits;

    /**
     * @MongoDB\Boolean
     */
    private $hasimages;

    /**
     * @MongoDB\Boolean
     */
    private $haslocations;

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
        $this->children = new \Doctrine\Common\Collections\ArrayCollection();
        $this->plantunits = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set level
     *
     * @param int $level
     */
    public function setLevel($level)
    {
        $this->level = $level;
    }

    /**
     * Get level
     *
     * @return int $level
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Set parent
     *
     * @param Plantnet\DataBundle\Document\Taxon $parent
     */
    public function setParent(\Plantnet\DataBundle\Document\Taxon $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Get parent
     *
     * @return Plantnet\DataBundle\Document\Taxon $parent
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add children
     *
     * @param Plantnet\DataBundle\Document\Taxon $children
     */
    public function addChildren(\Plantnet\DataBundle\Document\Taxon $children)
    {
        $this->children[] = $children;
    }

    /**
    * Remove children
    *
    * @param <variableType$children
    */
    public function removeChildren(\Plantnet\DataBundle\Document\Taxon $children)
    {
        $this->children->removeElement($children);
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
     * Set nbpunits
     *
     * @param int $nbpunits
     */
    public function setNbpunits($nbpunits)
    {
        $this->nbpunits = $nbpunits;
    }

    /**
     * Get nbpunits
     *
     * @return int $nbpunits
     */
    public function getNbpunits()
    {
        return $this->nbpunits;
    }

    /**
     * Set hasimages
     *
     * @param boolean $hasimages
     */
    public function setHasimages($hasimages)
    {
        $this->hasimages = $hasimages;
    }

    /**
     * Get hasimages
     *
     * @return boolean $hasimages
     */
    public function getHasimages()
    {
        return $this->hasimages;
    }

    /**
     * Set haslocations
     *
     * @param boolean $haslocations
     */
    public function setHaslocations($haslocations)
    {
        $this->haslocations = $haslocations;
    }

    /**
     * Get haslocations
     *
     * @return boolean $haslocations
     */
    public function getHaslocations()
    {
        return $this->haslocations;
    }
}
