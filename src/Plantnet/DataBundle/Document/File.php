<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @MongoDB\Document
 */
class File
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\Hash
     */
    protected $attribute;

    
    /**
     * @MongoDB\File
     * @Assert\File(maxSize = "30M", mimeTypes = {
     *   "image/jpeg",
     *   "image/png"
     * })
     */
    protected $File;


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
     * Set attribute
     *
     * @param hash $attribute
     */
    public function setAttribute($attribute)
    {
        $this->attribute = $attribute;
    }

    /**
     * Get attribute
     *
     * @return hash $attribute
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * Set File
     *
     * @param file $file
     */
    public function setFile($file)
    {
        $this->File = $file;
    }

    /**
     * Get File
     *
     * @return file $file
     */
    public function getFile()
    {
        return $this->File;
    }
}