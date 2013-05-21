<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

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
     * Get children
     *
     * @return Doctrine\Common\Collections\Collection $children
     */
    public function getChildren()
    {
        return $this->children;
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
}
