<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\EmbeddedDocument
 */
class Property
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
    protected $type;

    /**
     * @MongoDB\Boolean
     */
    private $main;

    /**
     * @MongoDB\Boolean
     */
    private $details;

    /**
     * @MongoDB\Boolean
     */
    private $search;

    /**
     * @MongoDB\Int
     */
    private $sortorder;

    /**
     * @MongoDB\Int
     */
    private $taxolevel;

    /**
     * @MongoDB\String
     */
    protected $taxolabel;

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
     * Set main
     *
     * @param boolean $main
     */
    public function setMain($main)
    {
        $this->main = $main;
    }

    /**
     * Get main
     *
     * @return boolean $main
     */
    public function getMain()
    {
        return $this->main;
    }

    /**
     * Set details
     *
     * @param boolean $details
     */
    public function setDetails($details)
    {
        $this->details = $details;
    }

    /**
     * Get details
     *
     * @return boolean $details
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Set search
     *
     * @param boolean $search
     */
    public function setSearch($search)
    {
        $this->search = $search;
    }

    /**
     * Get search
     *
     * @return boolean $search
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * Set type
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return string $type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set sortorder
     *
     * @param int $sortorder
     */
    public function setSortorder($sortorder)
    {
        $this->sortorder = $sortorder;
    }

    /**
     * Get sortorder
     *
     * @return int $sortorder
     */
    public function getSortorder()
    {
        return $this->sortorder;
    }

    /**
     * Set taxolevel
     *
     * @param int $taxolevel
     */
    public function setTaxolevel($taxolevel)
    {
        $this->taxolevel = $taxolevel;
    }

    /**
     * Get taxolevel
     *
     * @return int $taxolevel
     */
    public function getTaxolevel()
    {
        return $this->taxolevel;
    }

    /**
     * Set taxolabel
     *
     * @param string $taxolabel
     */
    public function setTaxolabel($taxolabel)
    {
        $this->taxolabel = $taxolabel;
    }

    /**
     * Get taxolabel
     *
     * @return string $taxolabel
     */
    public function getTaxolabel()
    {
        return $this->taxolabel;
    }
}
