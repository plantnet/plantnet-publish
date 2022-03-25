<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\OtherRepository")
 */
class Other
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $identifier;

    /**
     * @MongoDB\Hash
     */
    protected $property;
    
    /**
     * @MongoDB\Field(type="string")
     */
    protected $idparent;

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Plantunit",
     *      inversedBy="others"
     *  )
     */
    private $plantunit;

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Module",
     *      inversedBy="others"
     *  )
     */
    private $module;

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
     * Set property
     *
     * @param hash $property
     */
    public function setProperty($property)
    {
        $this->property = $property;
    }

    /**
     * Get property
     *
     * @return hash $property
     */
    public function getProperty()
    {
        return $this->property;
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
     * Set plantunit
     *
     * @param Plantnet\DataBundle\Document\Plantunit $plantunit
     */
    public function setPlantunit(\Plantnet\DataBundle\Document\Plantunit $plantunit)
    {
        $this->plantunit = $plantunit;
    }

    /**
     * Get plantunit
     *
     * @return Plantnet\DataBundle\Document\Plantunit $plantunit
     */
    public function getPlantunit()
    {
        return $this->plantunit;
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
}
