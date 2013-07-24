<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\EmbeddedDocument
 */
class Definition
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
    protected $definition;

    /**
     * @MongoDB\String
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
}
