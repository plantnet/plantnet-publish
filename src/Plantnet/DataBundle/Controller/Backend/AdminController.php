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
     * @Template("PlantnetDataBundle:Backend:index.html.twig")
     */
    public function indexAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collections = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        return $this->render('PlantnetDataBundle:Backend:index.html.twig',array(
            'collections' => $collections,
            'current' => 'administration'
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
        return $this->render('PlantnetDataBundle:Backend\Admin:collection_view.html.twig', array(
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
        $mod = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=> $module,
                'collection.id' => $collection->getId()
            ));
        if (!$mod) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        if($mod->getParent()){
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($mod->getParent()->getId());
        }else{
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($mod->getId());
        }
        $display = array();
        $field = $module->getProperties();
        foreach($field as $row){
            if($row->getMain() == true){
                $display[] = $row->getName();
            }
        }
        switch ($mod->getType())
        {
            case 'text':
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->field('module')->references($module)
                    ->hydrate(true);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Backend\Admin:datagrid.html.twig', array(
                    'paginator' => $paginator,
                    'field' => $field,
                    'collection' => $collection,
                    'module' => $module,
                    'display' => $display
                ));
                break;
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
                    'module' => $mod
                ));
                break;
            case 'locality':
                $db=$this->getDataBase($user);
                $m=new \Mongo();
                $c_plantunits=$m->$db->Plantunit->find(
                    array('module.$id'=>new \MongoId($module->getId())),
                    array('_id'=>1)
                );
                $id_plantunits=array();
                foreach($c_plantunits as $id=>$p)
                {
                    $id_plantunits[]=new \MongoId($id);
                }
                unset($c_plantunits);
                $locations=array();
                $c_locations=$m->$db->Location->find(
                    array(
                        'plantunit.$id'=>array('$in'=>$id_plantunits),
                        'module.$id'=>new \MongoId($mod->getId())
                    ),
                    array('_id'=>1,'latitude'=>1,'longitude'=>1,'title1'=>1,'title2'=>1)
                );
                unset($id_plantunits);
                foreach($c_locations as $id=>$l)
                {
                    $loc=array();
                    $loc['id']=$id;
                    $loc['latitude']=$l['latitude'];
                    $loc['longitude']=$l['longitude'];
                    $loc['title1']=$l['title1'];
                    $loc['title2']=$l['title2'];
                    $locations[]=$loc;
                }
                unset($c_locations);
                unset($m);
                $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                return $this->render('PlantnetDataBundle:Backend\Admin:map.html.twig',array(
                    'collection' => $collection,
                    'module' => $mod,
                    'locations' => $locations,
                    'layers' => $layers
                ));
                break;
        }
    }

    public function page_listAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $pages = $dm->getRepository('PlantnetDataBundle:Page')
            ->findAll();
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
        return array(
            'page' => $page,
            'edit_form' => $editForm->createView()
        );
    }

    /**
     * Edits an existing Page entity.
     *
     * @Route("/page/{name}/update", name="page_update")
     * @Method("post")
     * @Template("PlantnetBotaBundle:Backend\Admin:page_edit.html.twig")
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
        return array(
            'page' => $page,
            'edit_form' => $editForm->createView()
        );
    }
}
