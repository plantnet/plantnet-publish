<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\DefinitionRepository")
 */
class Definition
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $name;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $displayedname;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $definition;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $path;

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Glossary",
     *      inversedBy="definitions"
     *  )
     */
    private $glossary;

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Definition",
     *      nullable="true",
     *      inversedBy="children"
     *  )
     */
    private $parent;

    /**
     * @MongoDB\ReferenceMany(
     *      targetDocument="Definition",
     *      sort={"name"="asc"},
     *      nullable="true",
     *      mappedBy="parent",
     *      cascade={"remove"}
     *  )
     */
    private $children = array();

    /**
     * @MongoDB\Field(type="bool")
     */
    private $haschildren;

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
     * To String
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
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
     * Set displayedname
     *
     * @param string $displayedname
     */
    public function setDisplayedname($displayedname)
    {
        $this->displayedname = $displayedname;
    }

    /**
     * Get displayedname
     *
     * @return string $displayedname
     */
    public function getDisplayedname()
    {
        return $this->displayedname;
    }

    /**
     * Set definition
     *
     * @param string $definition
     */
    public function setDefinition($definition)
    {
        $this->definition = $definition;
    }

    /**
     * Get definition
     *
     * @return string $definition
     */
    public function getDefinition()
    {
        return $this->definition;
    }
    
    /**
     * Set path
     *
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Get path
     *
     * @return string $path
     */
    public function getPath()
    {
        return $this->path;
    }
    
    /**
     * Set glossary
     *
     * @param Plantnet\DataBundle\Document\Glossary $glossary
     */
    public function setGlossary(\Plantnet\DataBundle\Document\Glossary $glossary)
    {
        $this->glossary = $glossary;
    }

    /**
     * Get glossary
     *
     * @return Plantnet\DataBundle\Document\Glossary $glossary
     */
    public function getGlossary()
    {
        return $this->glossary;
    }

    /**
     * Set parent
     *
     * @param Plantnet\DataBundle\Document\Definition $parent
     */
    public function setParent(\Plantnet\DataBundle\Document\Definition $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Get parent
     *
     * @return Plantnet\DataBundle\Document\Definition $parent
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add children
     *
     * @param Plantnet\DataBundle\Document\Definition $children
     */
    public function addChildren(\Plantnet\DataBundle\Document\Definition $children)
    {
        $this->children[] = $children;
    }

    /**
    * Remove children
    *
    * @param <variableType$children
    */
    public function removeChildren(\Plantnet\DataBundle\Document\Definition $children)
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
