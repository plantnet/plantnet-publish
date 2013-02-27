<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Vich\GeographicalBundle\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @MongoDB\Document
 * @Vich\Geographical
 */
class Location
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\Hash
     */
    protected $property;

    /**
     * @MongoDB\Float
     */
    protected $latitude;

    /**
     * @MongoDB\Float
     */
    protected $longitude;
    
    /**
     * @MongoDB\ReferenceOne(targetDocument="Plantunit", simple=true)
     */
    private $plantunit;

    /**
     * @MongoDB\String
     */
    protected $identifier;

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
     * Set latitude
     *
     * @param float $latitude
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;
    }

    /**
     * Get latitude
     *
     * @return float $latitude
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set longitude
     *
     * @param float $longitude
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;
    }

    /**
     * Get longitude
     *
     * @return float $longitude
     */
    public function getLongitude()
    {
        return $this->longitude;
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
}