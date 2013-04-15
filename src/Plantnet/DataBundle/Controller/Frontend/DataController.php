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
        // $dm = $this->get('doctrine.odm.mongodb.document_manager');
        // $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);

        // $plantunitFinder=$this->container->get('fos_elastica.index.bota.plantunit');
        // $resultSet=$plantunitFinder->search('Artabotrys');
        // echo count($resultSet);


        // $plantunitFinder=$this->container->get('fos_elastica.finder.bota.plantunit');
        // $boolQuery=new \Elastica_Query_Bool();
        // $fieldQuery=new \Elastica_Query_Text();
        // $fieldQuery->setFieldQuery('Climbing Mode', 'Not described');
        // $boolQuery->addMust($fieldQuery);
        // $data=$plantunitFinder->find($boolQuery,1000);
        // echo count($data);




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
        return $this->render('PlantnetDataBundle:Frontend\Collection:collection.html.twig', array(
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
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name'=> $module, 'collection.id' => $collection->getId()));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display = array();
        $field = $module->getProperties();
        $module_parent=null;
        if($module->getParent()){
            $module_parent = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($module->getParent()->getId());
            $field = $module_parent->getProperties();
        }
        foreach($field as $row){
            if($row->getMain() == true){
                $display[] = $row->getName();
            }
        }
        switch ($module->getType())
        {
            case 'text':
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->field('module')->references($module);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Frontend\Module:datagrid.html.twig', array(
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
                if(!$module_parent){
                    throw $this->createNotFoundException('Unable to find Module entity.');
                }
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->field('module')->references($module);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(15);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Frontend\Module:gallery.html.twig', array(
                    'project' => $project,
                    'current' => 'collection',
                    'paginator' => $paginator,
                    'display' => $display,
                    'collection' => $collection,
                    'module_parent' => $module_parent,
                    'module' => $module,
                    'type' => 'images'
                ));
                break;
            case 'locality':
                if(!$module_parent){
                    throw $this->createNotFoundException('Unable to find Module entity.');
                }
                $db=$this->get_prefix().$project;
                $m=new \Mongo();
                $plantunits=array();
                $c_plantunits=$m->$db->Plantunit->find(
                    array('module.$id'=>new \MongoId($module_parent->getId())),
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
                        'module.$id'=>new \MongoId($module->getId())
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
                return $this->render('PlantnetDataBundle:Frontend\Module:map.html.twig',array(
                    'project' => $project,
                    'current' => 'collection',
                    'collection' => $collection,
                    'module_parent' => $module_parent,
                    'module' => $module,
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
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name' => $module, 'collection.id' => $collection->getId()));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display = array();
        $field = $module->getProperties();
        foreach($field as $row){
            if($row->getDetails() == true){
                $display[] = $row->getName();
            }
        }
        $plantunit = $dm->getRepository('PlantnetDataBundle:Plantunit')
            ->findOneBy(array('module.id' => $module->getId(), 'id' => $id));
        if(!$plantunit){
            throw $this->createNotFoundException('Unable to find Plantunit entity.');
        }
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
        return $this->render('PlantnetDataBundle:Frontend\Plantunit:details.html.twig', array(
            'idplantunit' => $plantunit->getId(),
            'project' => $project,
            'current' => 'collection',
            'display' => $display,
            'layers' => $layers,
            'collection' => $collection,
            'module' => $module,
            'plantunit' => $plantunit
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
            $form->add('field_'.$field_num,'text',array('required'=>false,'label'=>$field));
            $form->add('name_field_'.$field_num,'hidden',array('required'=>true,'data'=>$field));
            $field_num++;
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
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name'=> $module, 'collection.id' => $collection->getId()));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        if($module->getParent()){
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($module->getParent()->getId());
        }
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $fields = array();
        $field = $module->getProperties();
        foreach($field as $row){
            if($row->getSearch() == true){
                $fields[] = $row->getName();
            }
        }
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers_search.xml',0,true);
        $form=$this->createModuleSearchForm($fields);
        return $this->render('PlantnetDataBundle:Frontend\Module:module_search.html.twig', array(
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
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name'=> $module, 'collection.id' => $collection->getId()));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        if($module->getParent()){
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($module->getParent()->getId());
        }
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $fields = array();
        $display = array();
        $field = $module->getProperties();
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
            $ids_punit=array();
            // Locations
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
                    $ids_punit[]=$location['plantunit']['$id']->{'$id'};
                }
                unset($locations);
            }
            // Fields
            $fields=array();
            foreach($data as $key=>$val)
            {
                if(substr_count($key,'name_field_'))
                {
                    if(isset($data[str_replace('name_','',$key)])&&!empty($data[str_replace('name_','',$key)]))
                    {
                        $fields[$val]=$data[str_replace('name_','',$key)];
                    }
                }
            }
            // Search
            $paginator=null;
            $nbResults=0;
            if(count($ids_punit)||count($fields))
            {
                $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->field('module.id')->equals($module->getId());
                if(count($ids_punit))
                {
                    $plantunits->field('_id')->in($ids_punit);
                }
                if(count($fields))
                {
                    foreach($fields as $key=>$value)
                    {
                        $plantunits->field('attributes.'.$key)->equals(new \MongoRegex('/.*'.$value.'.*/i'));
                    }
                }
                $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($plantunits));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                $nbResults=$paginator->getNbResults();
            }
            return $this->render('PlantnetDataBundle:Frontend\Module:module_result.html.twig', array(
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
        return $this->render('PlantnetDataBundle:Frontend\Module:module_result.html.twig', array(
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
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        return $this->render('PlantnetDataBundle:Frontend\Pages:credits.html.twig', array(
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
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        return $this->render('PlantnetDataBundle:Frontend\Pages:mentions.html.twig', array(
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
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        return $this->render('PlantnetDataBundle:Frontend\Pages:contacts.html.twig', array(
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