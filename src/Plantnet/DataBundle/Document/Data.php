<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\EmbeddedDocument
 */
class Data
{
    /**
     * @MongoDB\Field(type="string")
     */
    protected $attribute;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $value;
    
    /**
     * @MongoDB\Field(type="string")
     */
    protected $type;

    /**
     * @MongoDB\Field(type="bool")ean
     */
    private $main;

    /**
     * @MongoDB\Field(type="bool")ean
     */
    private $details;

    /**
     * Set attribute
     *
     * @param string $attribute
     */
    public function setAttribute($attribute)
    {
        $this->attribute = $attribute;
    }

    /**
     * Get attribute
     *
     * @return string $attribute
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * Set value
     *
     * @param string $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Get value
     *
     * @return string $value
     */
    public function getValue()
    {
        return $this->value;
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
}
