<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\LocationRepository")
 * @MongoDB\Index(keys={"coordinates"="2d"})
 */
class Location
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
     * @MongoDB\Field(type="string")
     */
    protected $title1;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $title2;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $title3;

    /**
     * @MongoDB\Hash
     */
    protected $property;

    /**
     * @MongoDB\Field(type="float")
     */
    protected $latitude;

    /**
     * @MongoDB\Field(type="float")
     */
    protected $longitude;

    /**
     * @MongoDB\EmbedOne(targetDocument="Coordinates")
     */
    protected $coordinates;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $idparent;
    
    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Plantunit",
     *      inversedBy="locations"
     *  )
     */
    private $plantunit;

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Module",
     *      inversedBy="locations"
     *  )
     */
    private $module;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Taxon")
     */
    private $taxonsrefs = array();

    public function __construct()
    {
        $this->taxonsrefs = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set title3
     *
     * @param string $title3
     */
    public function setTitle3($title3)
    {
        $this->title3 = $title3;
    }

    /**
     * Get title3
     *
     * @return string $title3
     */
    public function getTitle3()
    {
        return $this->title3;
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
     * Set coordinates
     *
     * @param Plantnet\DataBundle\Document\Coordinates $coordinates
     */
    public function setCoordinates(\Plantnet\DataBundle\Document\Coordinates $coordinates)
    {
        $this->coordinates = $coordinates;
    }

    /**
     * Get coordinates
     *
     * @return Plantnet\DataBundle\Document\Coordinates $coordinates
     */
    public function getCoordinates()
    {
        return $this->coordinates;
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

    /**
     * Add taxonsrefs
     *
     * @param Plantnet\DataBundle\Document\Taxon $taxonsrefs
     */
    public function addTaxonsref(\Plantnet\DataBundle\Document\Taxon $taxonsrefs)
    {
        $this->taxonsrefs[] = $taxonsrefs;
    }

    /**
    * Remove taxonsrefs
    *
    * @param <variableType$taxonsrefs
    */
    public function removeTaxonsref(\Plantnet\DataBundle\Document\Taxon $taxonsrefs)
    {
        $this->taxonsrefs->removeElement($taxonsrefs);
    }

    /**
     * Add taxonsrefs
     *
     * @param Plantnet\DataBundle\Document\Taxon $taxonsrefs
     */
    public function addTaxonsrefs(\Plantnet\DataBundle\Document\Taxon $taxonsrefs)
    {
        $this->taxonsrefs[] = $taxonsrefs;
    }

    /**
     * Get taxonsrefs
     *
     * @return Doctrine\Common\Collections\Collection $taxonsrefs
     */
    public function getTaxonsrefs()
    {
        return $this->taxonsrefs;
    }
}
