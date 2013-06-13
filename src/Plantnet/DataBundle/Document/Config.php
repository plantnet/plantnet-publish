<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\ConfigRepository")
 */
class Config
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\EmbedOne(targetDocument="Language")
     */
    protected $defaultlanguage;

    /**
     * @MongoDB\EmbedMany(targetDocument="Language")
     */
    protected $availablelanguages;

    /**
     * @MongoDB\EmbedMany(targetDocument="Language")
     */
    protected $customlanguages;

    /**
     * To String
     *
     * @return string
     */
    public function __toString()
    {
        return 'Config';
    }

    public function __construct()
    {
        $this->availablelanguages = new \Doctrine\Common\Collections\ArrayCollection();
        $this->customlanguages = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set defaultlanguage
     *
     * @param Plantnet\DataBundle\Document\Language $defaultlanguage
     */
    public function setDefaultlanguage(\Plantnet\DataBundle\Document\Language $defaultlanguage)
    {
        $this->defaultlanguage = $defaultlanguage;
    }

    /**
     * Get defaultlanguage
     *
     * @return Plantnet\DataBundle\Document\Language $defaultlanguage
     */
    public function getDefaultlanguage()
    {
        return $this->defaultlanguage;
    }

    /**
     * Add availablelanguages
     *
     * @param Plantnet\DataBundle\Document\Language $availablelanguages
     */
    public function addAvailablelanguage(\Plantnet\DataBundle\Document\Language $availablelanguages)
    {
        $this->availablelanguages[] = $availablelanguages;
    }

    /**
    * Remove availablelanguages
    *
    * @param <variableType$availablelanguages
    */
    public function removeAvailablelanguage(\Plantnet\DataBundle\Document\Language $availablelanguages)
    {
        $this->availablelanguages->removeElement($availablelanguages);
    }

    /**
     * Add availablelanguages
     *
     * @param Plantnet\DataBundle\Document\Language $availablelanguages
     */
    public function addAvailablelanguages(\Plantnet\DataBundle\Document\Language $availablelanguages)
    {
        $this->availablelanguages[] = $availablelanguages;
    }

    /**
     * Get availablelanguages
     *
     * @return Doctrine\Common\Collections\Collection $availablelanguages
     */
    public function getAvailablelanguages()
    {
        return $this->availablelanguages;
    }
    
    /**
     * Add customlanguages
     *
     * @param Plantnet\DataBundle\Document\Language $customlanguages
     */
    public function addCustomlanguage(\Plantnet\DataBundle\Document\Language $customlanguages)
    {
        $this->customlanguages[] = $customlanguages;
    }

    /**
    * Remove customlanguages
    *
    * @param <variableType$customlanguages
    */
    public function removeCustomlanguage(\Plantnet\DataBundle\Document\Language $customlanguages)
    {
        $this->customlanguages->removeElement($customlanguages);
    }

    /**
     * Add customlanguages
     *
     * @param Plantnet\DataBundle\Document\Language $customlanguages
     */
    public function addCustomlanguages(\Plantnet\DataBundle\Document\Language $customlanguages)
    {
        $this->customlanguages[] = $customlanguages;
    }

    /**
     * Get customlanguages
     *
     * @return Doctrine\Common\Collections\Collection $customlanguages
     */
    public function getCustomlanguages()
    {
        return $this->customlanguages;
    }
}
