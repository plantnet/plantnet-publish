<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Symfony\Component\HttpFoundation\Response;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;

use Plantnet\DataBundle\Document\Page,
    Plantnet\DataBundle\Form\Type\PageType;

//?
use Plantnet\DataBundle\Document\Plantunit;
use Plantnet\DataBundle\Document\Image;
use Plantnet\DataBundle\Document\Location;
use Plantnet\DataBundle\Document\Other;

/**
 * Admin controller.
 *
 * @Route("/admin")
 */
class AdminController extends Controller
{
    private function getDataBase($user=null,$dm=null)
    {
        if($user)
        {
            return $user->getDbName();
        }
        elseif($dm)
        {
            return $dm->getConfiguration()->getDefaultDB();
        }
        return $this->container->getParameter('mdb_base');
    }

    public function displayTitleAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        return $this->render('PlantnetDataBundle:Backend\Admin:title.html.twig', array(
            'title'=>str_replace('bota_','',$user->getDbName())
        ));
    }

    /**
     * @Route("/", name="admin_index")
     * @Template()
     */
    public function indexAction()
    {
        return $this->render('PlantnetDataBundle:Backend:index.html.twig',array(
            'current' => 'index'
        ));
    }

    /**
     * @Route("/collection/{collection}", name="admin_collection_view")
     * @Template()
     */
    public function collectionAction($collection)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'name'=>$collection
            ));
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        return $this->render('PlantnetDataBundle:Backend\Admin:collection.html.twig', array(
            'collection'=>$collection
        ));
    }

    /**
     * @Route("/collection/{collection}/module/{module}", name="admin_module_view")
     * @Template()
     */
    public function moduleAction($collection, $module)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'name'=>$collection
            ));
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=> $module,
                'collection.id' => $collection->getId()
            ));
        if (!$module||$module->getType()!='text') {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display = array();
        $field = $module->getProperties();
        foreach($field as $row){
            if($row->getMain() == true){
                $display[] = $row->getId();
            }
        }
        $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->field('module')->references($module)
            ->hydrate(true);
        $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
        $paginator->setMaxPerPage(50);
        $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
        return $this->render('PlantnetDataBundle:Backend\Admin:datagrid.html.twig', array(
            'paginator' => $paginator,
            'collection' => $collection,
            'module' => $module,
            'display' => $display
        ));
    }

    /**
     * @Route("/collection/{collection}/module/{module}/module/{submodule}", name="admin_submodule_view")
     * @Template()
     */
    public function submoduleAction($collection, $module, $submodule)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'name'=>$collection
            ));
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=> $module,
                'collection.id' => $collection->getId()
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $mod = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=> $submodule,
                'parent.id'=>$module->getId(),
                'collection.id' => $collection->getId()
            ));
        if (!$mod||$mod->getType()=='text') {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display = array();
        $field = $module->getProperties();
        foreach($field as $row){
            if($row->getMain() == true){
                $display[] = $row->getId();
            }
        }
        switch ($mod->getType())
        {
            case 'image':
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->field('module')->references($mod)
                    ->hydrate(false);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(20);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Backend\Admin:gallery.html.twig', array(
                    'paginator' => $paginator,
                    'collection' => $collection,
                    'module' => $mod,
                    'module_parent' => $module,
                ));
                break;
            case 'locality':
                $db=$this->getDataBase($user);
                $m=new \Mongo();
                $plantunits=array();
                $c_plantunits=$m->$db->Plantunit->find(
                    array('module.$id'=>new \MongoId($module->getId())),
                    array('_id'=>1,'title1'=>1,'title2'=>1)
                );
                foreach($c_plantunits as $id=>$p)
                {
                    $plant=array();
                    $plant['title1']='';
                    $plant['title2']='';
                    if(isset($p['title1']))
                    {
                        $plant['title1']=$p['title1'];
                    }
                    if(isset($p['title2']))
                    {
                        $plant['title2']=$p['title2'];
                    }
                    $plantunits[$id]=$plant;
                }
                unset($c_plantunits);
                $locations=array();
                $c_locations=$m->$db->Location->find(
                    array(
                        'module.$id'=>new \MongoId($mod->getId())
                    ),
                    array('_id'=>1,'latitude'=>1,'longitude'=>1,'plantunit.$id'=>1)
                );
                foreach($c_locations as $id=>$l)
                {
                    $loc=array();
                    $loc['id']=$id;
                    $loc['latitude']=$l['latitude'];
                    $loc['longitude']=$l['longitude'];
                    $loc['title1']='';
                    $loc['title2']='';
                    if(array_key_exists($l['plantunit']['$id']->{'$id'},$plantunits))
                    {
                        if(isset($plantunits[$l['plantunit']['$id']->{'$id'}]['title1']))
                        {
                            $loc['title1']=$plantunits[$l['plantunit']['$id']->{'$id'}]['title1'];
                        }
                        if(isset($plantunits[$l['plantunit']['$id']->{'$id'}]['title2']))
                        {
                            $loc['title2']=$plantunits[$l['plantunit']['$id']->{'$id'}]['title2'];
                        }
                    }
                    $locations[]=$loc;
                }
                unset($plantunits);
                unset($c_locations);
                unset($m);
                $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                return $this->render('PlantnetDataBundle:Backend\Admin:map.html.twig',array(
                    'collection' => $collection,
                    'module' => $mod,
                    'module_parent' => $module,
                    'locations' => $locations,
                    'layers' => $layers
                ));
                break;
            case 'other':
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Other')
                    ->field('module')->references($mod)
                    ->hydrate(false);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Backend\Admin:other.html.twig', array(
                    'paginator' => $paginator,
                    'collection' => $collection,
                    'module' => $mod,
                    'module_parent' => $module,
                ));
                break;
        }
    }

    public function page_listAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $pages = $dm->createQueryBuilder('PlantnetDataBundle:Page')
            ->sort('order','ASC')
            ->getQuery()
            ->execute();
        return $this->render('PlantnetDataBundle:Backend\Admin:page_list.html.twig',array(
            'pages' => $pages,
            'current' => 'administration'
        ));
    }

    /**
     * Displays a form to edit a page.
     *
     * @Route("/page/{name}/edit", name="page_edit")
     * @Template()
     */
    public function page_editAction($name)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $page = $dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'name'=>$name
            ));
        if (!$page) {
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        $editForm = $this->createForm(new PageType(), $page);
        return $this->render('PlantnetDataBundle:Backend\Admin:page_edit.html.twig',array(
            'page' => $page,
            'edit_form' => $editForm->createView(),
            'current' => 'pages'
        ));
    }

    /**
     * Edits an existing Page entity.
     *
     * @Route("/page/{name}/update", name="page_update")
     * @Method("post")
     * @Template()
     */
    public function page_updateAction($name)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $page = $dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'name'=>$name
            ));
        if (!$page) {
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        $editForm = $this->createForm(new PageType(), $page);
        $request = $this->getRequest();
        if ('POST' === $request->getMethod()) {
            $editForm->bindRequest($request);
            if ($editForm->isValid()) {
                $dm->persist($page);
                $dm->flush();
                return $this->redirect($this->generateUrl('page_edit', array('name' => $name)));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Admin:page_edit.html.twig',array(
            'page' => $page,
            'edit_form' => $editForm->createView(),
            'current' => 'pages'
        ));
    }
}
