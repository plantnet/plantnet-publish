<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique as MongoDBUnique;

use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\CollectionRepository")
 * @MongoDBUnique(fields="name")
 * @MongoDBUnique(fields="alias")
 * @MongoDBUnique(fields="url")
 */
class Collection implements Translatable
{
    /**
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @MongoDB\String
     * @Gedmo\Translatable
     */
    protected $name;

    /**
     * @MongoDB\String
     */
    protected $alias;

    /**
     * @MongoDB\String
     */
    protected $url;

    /**
     * @MongoDB\String
     * @Gedmo\Translatable
     */
    protected $description;
    
    /**
     * @MongoDB\ReferenceMany(
     *      targetDocument="Module",
     *      criteria={"type":"text"},
     *      sort={"name"="asc"},
     *      mappedBy="collection",
     *      cascade={"remove"}
     *  )
     */
    private $modules = array();

    /**
     * @MongoDB\Boolean
     */
    private $deleting;

    /**
     * @Gedmo\Locale
     * Used locale to override Translation listener`s locale
     * this is not a mapped field of entity metadata, just a simple property
     */
    private $locale;

    /**
     * To String
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

    public function __construct()
    {
        $this->modules = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set alias
     *
     * @param string $alias
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    /**
     * Get alias
     *
     * @return string $alias
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * Set url
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string $url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get description
     *
     * @return string $description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Add modules
     *
     * @param Plantnet\DataBundle\Document\Module $modules
     */
    public function addModule(\Plantnet\DataBundle\Document\Module $modules)
    {
        $this->modules[] = $modules;
    }

    /**
    * Remove modules
    *
    * @param <variableType$modules
    */
    public function removeModule(\Plantnet\DataBundle\Document\Module $modules)
    {
        $this->modules->removeElement($modules);
    }

    /**
     * Get modules
     *
     * @return Doctrine\Common\Collections\Collection $modules
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Set deleting
     *
     * @param boolean $deleting
     */
    public function setDeleting($deleting)
    {
        $this->deleting = $deleting;
    }

    /**
     * Get deleting
     *
     * @return boolean $deleting
     */
    public function getDeleting()
    {
        return $this->deleting;
    }

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }
}
