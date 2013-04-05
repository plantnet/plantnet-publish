<?php

namespace Plantnet\DataBundle\Controller\Frontend;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller,
	Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Validator\Constraints\Type as TypeConstraint;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;


/**
 * Default  controller.
 *
 * @Route("")
 */
class DataController extends Controller
{
    private function database_list()
    {
        //display databases without prefix
        $prefix=$this->get_prefix();
        $dbs_array=array();
        $connection=new \Mongo();
        $dbs=$connection->admin->command(array(
            'listDatabases'=>1
        ));
        foreach($dbs['databases'] as $db)
        {
            $db_name=$db['name'];
            if(substr_count($db_name,$prefix))
            {
                $dbs_array[]=str_replace($prefix,'',$db_name);
            }
        }
        return $dbs_array;
    }

    private function get_prefix()
    {
        return 'bota_';
    }

    /**
     * @Route("/", name="_index")
     * @Template()
     */
    public function indexAction()
    {
        $projects=$this->database_list();
        return $this->render('PlantnetDataBundle:Frontend:index.html.twig', array(
            'projects' => $projects,
            'current' => 'index'
        ));
    }

    /**
     * @Route("/project/{project}", name="_project")
     * @Template()
     */
    public function projectAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        $page = $dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'name'=>'home'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        return $this->render('PlantnetDataBundle:Frontend:project.html.twig', array(
            'project' => $project,
            'page' => $page,
            'collections' => $collections,
            'current' => 'project'
        ));
    }

    public function Collection_listAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        return $this->render('PlantnetDataBundle:Frontend\Collection:collection_list.html.twig', array(
            'project' => $project,
            'collections' => $collections
        ));
    }

    /**
     * @Route("/project/{project}/collection/{collection}", name="_collection")
     * @Template()
     */
    public function collectionAction($project, $collection)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        return $this->render('PlantnetDataBundle:Frontend:collection.html.twig', array(
            'project' => $project,
            'collection' => $collection,
            'current' => 'collection'
        ));
    }






















    

    

    /**
     * @Route("/project/{project}/collection/{collection}/{module}", name="_module")
     * @Template()
     */
    public function moduleAction($project, $collection, $module)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        $mod = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name'=> $module, 'collection.id' => $collection->getId()));
        if($mod->getParent()){
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($mod->getParent()->getId());
        }else{
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($mod->getId());
        }
        $display = array();
        $field = $mod->getProperties();
        foreach($field as $row){
            if($row->getMain() == true){
                $display[] = $row->getName();
            }
        }
        switch ($mod->getType())
        {
            case 'text':
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->field('module')->references($mod)
                    ->hydrate(false);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Frontend:datagrid.html.twig', array(
                    'project' => $project,
                    'current' => 'collection',
                    'paginator' => $paginator,
                    'field' => $field,
                    'collection' => $collection,
                    'module' => $module,
                    'type' => 'table',
                    'display' => $display
                ));
                break;
            case 'image':
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->field('module')->references($mod);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(20);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Frontend:gallery.html.twig', array(
                    'project' => $project,
                    'current' => 'collection',
                    'paginator' => $paginator,
                    'collection' => $collection,
                    'module' => $mod,
                    'type' => 'images'
                ));
                break;
            case 'locality':
                $db=$this->get_prefix().$project;
                $m=new \Mongo();
                $plantunits=array();
                $c_plantunits=$m->$db->Plantunit->find(
                    array('module.$id'=>new \MongoId($module->getId())),
                    array('_id'=>1,'title1'=>1,'title2'=>1)
                );
                foreach($c_plantunits as $id=>$p)
                {
                    $plant=array();
                    $plant['title1']=$p['title1'];
                    $plant['title2']=$p['title2'];
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
                return $this->render('PlantnetDataBundle:Frontend:map.html.twig',array(
                    'project' => $project,
                    'current' => 'collection',
                    'collection' => $collection,
                    'module' => $mod,
                    'type' => 'localisation',
                    'locations' => $locations,
                    'layers' => $layers
                ));
                break;
        }
    }

    /**
     * @Route("/project/{project}/collection/{collection}/{module}/details/{id}", name="_details")
     * @Template()
     */
    public function detailsAction($project, $collection, $module, $id)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $coll = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name' => $module, 'collection.id' => $coll->getId()));
        $display = array();
        $field = $module->getProperties();
        foreach($field as $row){
            if($row->getDetails() == true){
                $display[] = $row->getName();
            }
        }
        $plantunit = $dm->getRepository('PlantnetDataBundle:Plantunit')
            ->findOneBy(array('module.id' => $module->getId(), 'id' => $id));
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
        return $this->render('PlantnetDataBundle:Frontend:details.html.twig', array(
            'idplantunit' => $plantunit->getId(),
            'project' => $project,
            'current' => 'collection',
            'display' => $display,
            'layers' => $layers,
            'collection' => $coll,
            'module' => $module,
            'plantunit' => $plantunit
        ));
    }

    private function createSearchForm()
    {
        $defaults=null;
        $constraints=array(
            'y_lat_1_bottom_left'=>new TypeConstraint('float'),
            'x_lng_1_bottom_left'=>new TypeConstraint('float'),
            'y_lat_2_top_right'=>new TypeConstraint('float'),
            'x_lng_2_top_right'=>new TypeConstraint('float'),
        );
        $form=$this->createFormBuilder($defaults,array('constraints'=>$constraints))
            ->add('y_lat_1_bottom_left','hidden',array('required'=>false))
            ->add('x_lng_1_bottom_left','hidden',array('required'=>false))
            ->add('y_lat_2_top_right','hidden',array('required'=>false))
            ->add('x_lng_2_top_right','hidden',array('required'=>false))
            ->add('search','search',array('required'=>false,'label'=>false))
            ->getForm();
        return $form;
    }

    /**
     * @Route("/project/{project}/search", name="_search")
     * @Template()
     */
    public function searchAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers_search.xml',0,true);
        $form=$this->createSearchForm();
        return $this->render('PlantnetDataBundle:Frontend:search.html.twig', array(
            'project' => $project,
            'layers' => $layers,
            'form' => $form->createView(),
            'current' => 'search'
        ));
    }

    /**
     * @Route("/project/{project}/result", name="_result")
     * @Method("get")
     * @Template()
     */
    public function resultAction($project,Request $request)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $form=$this->createSearchForm();
        if($request->isMethod('GET'))
        {
            $form->bind($request);
            $data=$form->getData();
            $dm = $this->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
            $plantunits=array();
            $ids=array();
            if(isset($data['x_lng_1_bottom_left'])&&!empty($data['x_lng_1_bottom_left']))
            {
                $locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                    ->field('coordinates')->withinBox(
                        floatval($data['x_lng_1_bottom_left']),
                        floatval($data['y_lat_1_bottom_left']),
                        floatval($data['x_lng_2_top_right']),
                        floatval($data['y_lat_2_top_right']))
                    ->hydrate(false)
                    ->getQuery()
                    ->execute();
                foreach($locations as $location)
                {
                    $ids[]=$location['plantunit']['$id']->{'$id'};
                }
                unset($locations);
            }
            $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->field('_id')->in($ids);
            $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($plantunits));
            $paginator->setMaxPerPage(50);
            $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
            $nbResults=$paginator->getNbResults();
            return $this->render('PlantnetDataBundle:Frontend:result.html.twig', array(
                'project' => $project,
                'current' => 'search',
                'paginator' => $paginator,
                'nbResults' => $nbResults
            ));
        }
        else
        {
            return $this->redirect($this->generateUrl(
                '_search',
                array(
                    'project' => $project
                )
            ));
        }
        return $this->render('PlantnetDataBundle:Frontend:result.html.twig', array(
            'project' => $project,
            'current' => 'search'
        ));
    }

    private function createModuleSearchForm($fields)
    {
        $defaults=null;
        $constraints=array(
            'y_lat_1_bottom_left'=>new TypeConstraint('float'),
            'x_lng_1_bottom_left'=>new TypeConstraint('float'),
            'y_lat_2_top_right'=>new TypeConstraint('float'),
            'x_lng_2_top_right'=>new TypeConstraint('float'),
        );
        $form=$this->createFormBuilder($defaults,array('constraints'=>$constraints))
            ->add('y_lat_1_bottom_left','hidden',array('required'=>false))
            ->add('x_lng_1_bottom_left','hidden',array('required'=>false))
            ->add('y_lat_2_top_right','hidden',array('required'=>false))
            ->add('x_lng_2_top_right','hidden',array('required'=>false));
        $field_num=0;
        foreach($fields as $field)
        {
            $form->add('field_'.$field_num++,'text',array('required'=>false,'label'=>$field));
        }
        $form=$form->getForm();
        return $form;
    }

    /**
     * @Route("/project/{project}/collection/{collection}/{module}/search", name="_module_search")
     * @Template()
     */
    public function module_searchAction($project, $collection, $module)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        $mod = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name'=> $module, 'collection.id' => $collection->getId()));
        if($mod->getParent()){
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($mod->getParent()->getId());
        }else{
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($mod->getId());
        }
        $fields = array();
        $field = $mod->getProperties();
        foreach($field as $row){
            if($row->getSearch() == true){
                $fields[] = $row->getName();
            }
        }
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers_search.xml',0,true);
        $form=$this->createModuleSearchForm($fields);
        return $this->render('PlantnetDataBundle:Frontend:module_search.html.twig', array(
            'project' => $project,
            'collection' => $collection,
            'module' => $module,
            'layers' => $layers,
            'form' => $form->createView(),
            'current' => 'collection'
        ));
    }

    /**
     * @Route("/project/{project}/collection/{collection}/{module}/result", name="_module_result")
     * @Method("get")
     * @Template()
     */
    public function module_resultAction($project, $collection, $module, Request $request)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        $mod = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name'=> $module, 'collection.id' => $collection->getId()));
        if($mod->getParent()){
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($mod->getParent()->getId());
        }else{
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($mod->getId());
        }
        $fields = array();
        $display = array();
        $field = $mod->getProperties();
        foreach($field as $row){
            if($row->getSearch() == true){
                $fields[] = $row->getName();
            }
            if($row->getMain() == true){
                $display[] = $row->getName();
            }
        }
        $form=$this->createModuleSearchForm($fields);
        if($request->isMethod('GET'))
        {
            $form->bind($request);
            $data=$form->getData();
            $dm = $this->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
            $plantunits=array();
            $ids=array();
            if(isset($data['x_lng_1_bottom_left'])&&!empty($data['x_lng_1_bottom_left']))
            {
                $locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                    ->field('coordinates')->withinBox(
                        floatval($data['x_lng_1_bottom_left']),
                        floatval($data['y_lat_1_bottom_left']),
                        floatval($data['x_lng_2_top_right']),
                        floatval($data['y_lat_2_top_right']))
                    ->hydrate(false)
                    ->getQuery()
                    ->execute();
                foreach($locations as $location)
                {
                    $ids[]=$location['plantunit']['$id']->{'$id'};
                }
                unset($locations);
            }
            $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->field('_id')->in($ids)
                ->field('module.id')->equals($module->getId());
            $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($plantunits));
            $paginator->setMaxPerPage(50);
            $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
            $nbResults=$paginator->getNbResults();
            return $this->render('PlantnetDataBundle:Frontend:module_result.html.twig', array(
                'project' => $project,
                'collection' => $collection,
                'module' => $module,
                'current' => 'collection',
                'paginator' => $paginator,
                'field' => $field,
                'display' => $display,
                'nbResults' => $nbResults
            ));
        }
        else
        {
            return $this->redirect($this->generateUrl(
                '_search',
                array(
                    'project' => $project
                )
            ));
        }
        return $this->render('PlantnetDataBundle:Frontend:result.html.twig', array(
            'project' => $project,
            'current' => 'search'
        ));
    }

    /**
     * @Route("/project/{project}/credits", name="_credits")
     * @Template()
     */
    public function creditsAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $page = $dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'name'=>'credits'
            ));
        return $this->render('PlantnetDataBundle:Frontend:credits.html.twig', array(
            'page' => $page,
            'project' => $project,
            'current' => 'credits'
        ));
    }

    /**
     * @Route("/project/{project}/mentions", name="_mentions")
     * @Template()
     */
    public function mentionsAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $page = $dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'name'=>'mentions'
            ));
        return $this->render('PlantnetDataBundle:Frontend:mentions.html.twig', array(
            'page' => $page,
            'project' => $project,
            'current' => 'mentions'
        ));
    }

    /**
     * @Route("/project/{project}/contacts", name="_contacts")
     * @Template()
     */
    public function contactsAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $page = $dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'name'=>'contacts'
            ));
        return $this->render('PlantnetDataBundle:Frontend:contacts.html.twig', array(
            'page' => $page,
            'project' => $project,
            'current' => 'contacts'
        ));
    }

    /**
     * @Route("/taxa", name="_taxa")
     * @Template()
     */
    /*
    public function taxonomyAction()
    {
        return $this->render('PlantnetDataBundle:Default:taxonomy.html.twig', array('current' => 'taxonomy'));
    }
    */
}