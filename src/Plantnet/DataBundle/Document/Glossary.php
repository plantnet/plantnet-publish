<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

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
     * @MongoDB\ReferenceMany(
     *      targetDocument="Definition",
     *      sort={"name"="asc"},
     *      mappedBy="glossary",
     *      cascade={"remove"}
     *  )
     */
    protected $definitions;

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Collection",
     *      inversedBy="glossary"
     *  )
     */
    private $collection;

    public function __construct()
    {
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
