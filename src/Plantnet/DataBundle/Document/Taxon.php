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
    protected $identifier;

    /**
     * @MongoDB\String
     */
    protected $name;

    /**
     * @MongoDB\String
     */
    protected $label;

    /**
     * @MongoDB\Int
     */
    private $level;

    /**
     * @MongoDB\Boolean
     */
    private $issynonym;

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
    private $children = array();

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Taxon",
     *      nullable="true",
     *      inversedBy="synonyms"
     *  )
     */
    private $chosen;

    /**
     * @MongoDB\ReferenceMany(
     *      targetDocument="Taxon",
     *      sort={"name"="asc"},
     *      nullable="true",
     *      mappedBy="chosen",
     *      cascade={"remove"}
     *  )
     */
    private $synonyms = array();

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
    private $hassynonyms;

    /**
     * @MongoDB\Boolean
     */
    private $haslocations;

    /**
     * @MongoDB\Boolean
     */
    private $haschildren;

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
        $this->synonyms = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set label
     *
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * Get label
     *
     * @return string $label
     */
    public function getLabel()
    {
        return $this->label;
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
     * Set issynonym
     *
     * @param boolean $issynonym
     */
    public function setIssynonym($issynonym)
    {
        $this->issynonym = $issynonym;
    }

    /**
     * Get issynonym
     *
     * @return boolean $issynonym
     */
    public function getIssynonym()
    {
        return $this->issynonym;
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
     * Set chosen
     *
     * @param Plantnet\DataBundle\Document\Taxon $chosen
     */
    public function setChosen(\Plantnet\DataBundle\Document\Taxon $chosen)
    {
        $this->chosen = $chosen;
    }

    /**
     * Get chosen
     *
     * @return Plantnet\DataBundle\Document\Taxon $chosen
     */
    public function getChosen()
    {
        return $this->chosen;
    }

    /**
     * Add synonyms
     *
     * @param Plantnet\DataBundle\Document\Taxon $synonyms
     */
    public function addSynonym(\Plantnet\DataBundle\Document\Taxon $synonyms)
    {
        $this->synonyms[] = $synonyms;
    }

    /**
    * Remove synonyms
    *
    * @param <variableType$synonyms
    */
    public function removeSynonym(\Plantnet\DataBundle\Document\Taxon $synonyms)
    {
        $this->synonyms->removeElement($synonyms);
    }

    /**
     * Add synonyms
     *
     * @param Plantnet\DataBundle\Document\Taxon $synonyms
     */
    public function addSynonyms(\Plantnet\DataBundle\Document\Taxon $synonyms)
    {
        $this->synonyms[] = $synonyms;
    }

    /**
     * Get synonyms
     *
     * @return Doctrine\Common\Collections\Collection $synonyms
     */
    public function getSynonyms()
    {
        return $this->synonyms;
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
     * Set hassynonyms
     *
     * @param boolean $hassynonyms
     */
    public function setHassynonyms($hassynonyms)
    {
        $this->hassynonyms = $hassynonyms;
    }

    /**
     * Get hassynonyms
     *
     * @return boolean $hassynonyms
     */
    public function getHassynonyms()
    {
        return $this->hassynonyms;
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

    /**
     * Set haschildren
     *
     * @param boolean $haschildren
     */
    public function setHaschildren($haschildren)
    {
        $this->haschildren = $haschildren;
    }

    /**
     * Get haschildren
     *
     * @return boolean $haschildren
     */
    public function getHaschildren()
    {
        return $this->haschildren;
    }
}
