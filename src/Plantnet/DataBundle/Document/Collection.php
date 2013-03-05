<?php
namespace Plantnet\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass="Plantnet\DataBundle\Repository\CollectionRepository")
 */

class Collection
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
    protected $alias;

    /**
     * @MongoDB\String
     */
    protected $description;
    
    
    /**
     * @MongoDB\ReferenceMany(targetDocument="Module", cascade={"remove"})
     */
    private $modules = array();

    /**
     * @MongoDB\ReferenceOne(targetDocument="Plantnet\UserBundle\Document\User")
     */
    protected $user;


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
        $tmp=mb_strtolower($alias,'UTF-8');
        $tmp=eregi_replace("[ ]+",'-',strtolower($tmp));
        $tmp=preg_replace('/([^.a-z0-9]+)/i','_',$tmp);
        $this->alias = $tmp;
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
    public function __construct()
    {
        $this->modules = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Add modules
     *
     * @param Plantnet\DataBundle\Document\Module $modules
     */
    public function addModules(\Plantnet\DataBundle\Document\Module $modules)
    {
        $this->modules[] = $modules;
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
     * Set user
     *
     * @param Plantnet\UserBundle\Document\User $user
     */
    public function setUser(\Plantnet\UserBundle\Document\User $user)
    {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return Plantnet\UserBundle\Document\User $user
     */
    public function getUser()
    {
        return $this->user;
    }
}