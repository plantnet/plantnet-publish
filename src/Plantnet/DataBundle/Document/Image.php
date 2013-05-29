<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\ImageRepository")
 */
class Image
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
    protected $title1;

    /**
     * @MongoDB\String
     */
    protected $title2;

    /**
     * @MongoDB\Hash
     */
    protected $property;

    /**
     * @MongoDB\String
     */
    protected $path;

    /**
     * @MongoDB\String
     */
    protected $copyright;
    
    /**
     * @MongoDB\String
     */
    protected $idparent;

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Plantunit",
     *      inversedBy="images"
     *  )
     */
    private $plantunit;

    /**
     * @MongoDB\ReferenceOne(
     *      targetDocument="Module",
     *      inversedBy="images"
     *  )
     */
    private $module;

    /**
     * @MongoDB\ReferenceMany(targetDocument="Taxon")
     */
    private $taxonsrefs;

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
     * Set copyright
     *
     * @param string $copyright
     */
    public function setCopyright($copyright)
    {
        $this->copyright = $copyright;
    }

    /**
     * Get copyright
     *
     * @return string $copyright
     */
    public function getCopyright()
    {
        return $this->copyright;
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
