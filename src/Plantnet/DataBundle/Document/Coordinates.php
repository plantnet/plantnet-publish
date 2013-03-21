<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @MongoDB\EmbeddedDocument
 */
class Coordinates
{
    /** @MongoDB\Float */
    protected $x;

    /** @MongoDB\Float */
    protected $y;


    /**
     * Set x
     *
     * @param float $x
     */
    public function setX($x)
    {
        $this->x = $x;
    }

    /**
     * Get x
     *
     * @return float $x
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     * Set y
     *
     * @param float $y
     */
    public function setY($y)
    {
        $this->y = $y;
    }

    /**
     * Get y
     *
     * @return float $y
     */
    public function getY()
    {
        return $this->y;
    }
}
