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

use Plantnet\DataBundle\Utils\StringHelp;


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
        $connection=new \MongoClient();
        $dbs=$connection->admin->command(array(
            'listDatabases'=>1
        ));
        foreach($dbs['databases'] as $db){
            $db_name=$db['name'];
            if(substr_count($db_name,$prefix)){
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
     * @Route("/", name="front_index")
     * @Template()
     */
    public function indexAction()
    {
        $projects=$this->database_list();
        return $this->render('PlantnetDataBundle:Frontend:index.html.twig',array(
            'projects'=>$projects,
            'current'=>'index'
        ));
    }

    /**
     * @Route("/project/{project}", name="front_project")
     * @Template()
     */
    public function projectAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        $page=$dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'name'=>'home'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        return $this->render('PlantnetDataBundle:Frontend:project.html.twig',array(
            'project'=>$project,
            'page'=>$page,
            'collections'=>$collections,
            'current'=>'project'
        ));
    }

    public function collection_listAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        return $this->render('PlantnetDataBundle:Frontend\Collection:collection_list.html.twig',array(
            'project'=>$project,
            'collections'=>$collections
        ));
    }

    /**
     * @Route("/project/{project}/collection/{collection}", name="front_collection")
     * @Template()
     */
    public function collectionAction($project,$collection)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        return $this->render('PlantnetDataBundle:Frontend\Collection:collection.html.twig',array(
            'project'=>$project,
            'collection'=>$collection,
            'current'=>'collection'
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}",
     *      defaults={"page"=1, "sortby"="null", "sortorder"="null"},
     *      name="front_module"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/page{page}",
     *      requirements={"page"="\d+"},
     *      defaults={"sortby"="null", "sortorder"="null"},
     *      name="front_module_paginated"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/page{page}/sort-{sortby}/order-{sortorder}",
     *      requirements={"page"="\d+", "sortby"="\w+", "sortorder"="null|asc|desc"},
     *      name="front_module_paginated_sorted"
     *  )
     * @Method("get")
     * @Template()
     */
    public function moduleAction($project,$collection,$module,$page,$sortby,$sortorder,Request $request)
    {
        $form_page=$request->query->get('form_page');
        if(!empty($form_page)){
            $page=$form_page;
        }
        if($this->container->get('request')->get('_route')=='front_module_paginated'&&$page==1){
            return $this->redirect($this->generateUrl('front_module',array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module
                )
            ),301);
        }
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='text'){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display=array();
        $order=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getMain()==true){
                $display[]=$row->getId();
            }
            if($row->getSortorder()){
                $order[$row->getSortorder()]=$row->getId();
            }
        }
        ksort($order);
        $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->field('module')->references($module);
        if($sortby!='null'&&$sortorder!='null'){
            if(in_array($sortby,$order)){
                unset($order[array_search($sortby,$order)]);
            }
            $queryBuilder->sort('attributes.'.$sortby,$sortorder);
        }
        if(count($order)){
            foreach($order as $num=>$prop){
                $queryBuilder->sort('attributes.'.$prop,'asc');
            }
        }
        $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
        try{
            $paginator->setMaxPerPage(50);
            $paginator->setCurrentPage($page);
        }
        catch(\Pagerfanta\Exception\NotValidCurrentPageException $e){
            throw $this->createNotFoundException('Page not found.');
        }
        return $this->render('PlantnetDataBundle:Frontend\Module:datagrid.html.twig',array(
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'paginator'=>$paginator,
            'display'=>$display,
            'page'=>$page,
            'sortby'=>$sortby,
            'sortorder'=>$sortorder,
            'current'=>'collection'
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/module/{submodule}",
     *      defaults={"page"=1},
     *      name="front_submodule"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/module/{submodule}/page{page}",
     *      requirements={"page"="\d+"},
     *      name="front_submodule_paginated"
     *  )
     * @Method("get")
     * @Template()
     */
    public function submoduleAction($project,$collection,$module,$submodule,$page,Request $request)
    {
        $form_page=$request->query->get('form_page');
        if(!empty($form_page)){
            $page=$form_page;
        }
        if($this->container->get('request')->get('_route')=='front_submodule_paginated'&&$page==1){
            return $this->redirect($this->generateUrl('front_submodule',array(
                    'project'=>$project,
                    'collection'=>$collection,
                    'module'=>$module,
                    'submodule'=>$submodule
                )
            ),301);
        }
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module_parent=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module_parent){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$submodule,
                'parent.id'=>$module_parent->getId(),
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()=='text'){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display=array();
        $field=$module_parent->getProperties();
        foreach($field as $row){
            if($row->getMain()==true){
                $display[]=$row->getId();
            }
        }
        switch($module->getType()){
            case 'image':
                $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->field('module')->references($module)
                    ->sort('title1','asc')
                    ->sort('title2','asc');
                /*
                // pour trouver les images manquantes avant export IDAO
                $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->field('module')->references($module)
                    ->getQuery()
                    ->execute();
                $nb=0;
                foreach($queryBuilder as $img)
                {
                    $file=$img->getModule()->getUploaddir().'/'.$img->getPath();
                    if(!file_exists(__dir__.'/../../../../../web/uploads/'.$file))
                    {
                        $nb++;
                        echo $img->getPlantunit()->getTitle2().' - '.$img->getPlantunit()->getTitle1().' - '.$img->getPath().'<br />';
                    }
                }
                echo $nb;
                exit;
                */
                $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                try{
                    $paginator->setMaxPerPage(15);
                    $paginator->setCurrentPage($page);
                }
                catch(\Pagerfanta\Exception\NotValidCurrentPageException $e){
                    throw $this->createNotFoundException('Page not found.');
                }
                return $this->render('PlantnetDataBundle:Frontend\Module:gallery.html.twig',array(
                    'project'=>$project,
                    'collection'=>$collection,
                    'module_parent'=>$module_parent,
                    'module'=>$module,
                    'paginator'=>$paginator,
                    'display'=>$display,
                    'current'=>'collection',
                ));
                break;
            case 'locality':
                $locations=array();
                $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                return $this->render('PlantnetDataBundle:Frontend\Module:map.html.twig',array(
                    'project'=>$project,
                    'collection'=>$collection,
                    'module'=>$module,
                    'module_parent'=>$module_parent,
                    'layers'=>$layers,
                    'locations'=>$locations,
                    'current'=>'collection'
                ));
                break;
        }
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/module/{submodule}/datamap",
     *      defaults={"page"=0},
     *      name="front_datamap"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/module/{submodule}/datamap/page{page}",
     *      requirements={"page"="\d+"},
     *      name="front_datamap_paginated"
     *  )
     * @Template()
     */
    public function datamapAction($project,$collection,$module,$submodule,$page)
    {
        $max_per_page=5000;
        $start=$page*$max_per_page;
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module_parent=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module_parent){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$submodule,
                'parent.id'=>$module_parent->getId(),
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='locality'){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getDetails()==true){
                $display[$row->getId()]=$row->getName();
            }
        }
        //data extract
        $db=$this->get_prefix().$project;
        $m=new \MongoClient();
        $locations=array();
        $c_locations=$m->$db->Location->find(
            array(
                'module.$id'=>new \MongoId($module->getId())
            ),
            array(
                '_id'=>1,
                'latitude'=>1,
                'longitude'=>1,
                'plantunit'=>1,
                'property'=>1,
                'title1'=>1,
                'title2'=>1
            )
        )->sort(array('_id'=>1))->limit($max_per_page)->skip($start);
        foreach($c_locations as $id=>$l){
            $loc=array();
            $loc['type']='Feature';
            $loc['id']=$id;
            $loc['geometry']=array(
                'type'=>'Point',
                'coordinates'=>array(
                    $l['longitude'],
                    $l['latitude']
                )
            );
            $loc['properties']=array(
                'punit'=>'',
                'title1'=>$l['title1'],
                'title2'=>$l['title2'],
                'loc_data'=>''
            );
            $loc['properties']['punit']=$this->get('router')->generate('front_details',array(
                'project'=>$project,
                'collection'=>$collection->getUrl(),
                'module'=>$module_parent->getUrl(),
                'id'=>$l['plantunit']['$id']->{'$id'}
            ));
            foreach($l['property'] as $key=>$val){
                if(array_key_exists($key,$display)){
                    $loc['properties']['loc_data']=$loc['properties']['loc_data'].$display[$key].': '.$val."\n";
                }
            }
            $locations[]=$loc;
        }
        unset($c_locations);
        $total=$m->$db->Location->find(
            array(
                'module.$id'=>new \MongoId($module->getId())
            )
        )->count();
        unset($m);
        $next=$page+1;
        if($start+$max_per_page>=$total){
            $next=-1;
        }
        $done=round(($start+$max_per_page)*100/$total);
        if($done>100){
            $done=100;
        }
        $return=array(
            'type'=>'FeatureCollection',
            'features'=>$locations,
            'next'=>$next,
            'done'=>$done
        );
        $response=new Response(json_encode($return));
        $response->headers->set('Content-Type','application/json');
        return $response;
        exit;
    }

    /**
     * @Route("/project/{project}/collection/{collection}/{module}/details/{id}", name="front_details")
     * @Template()
     */
    public function detailsAction($project,$collection,$module,$id)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getDetails()==true){
                $display[]=$row->getId();
            }
        }
        $plantunit=$dm->getRepository('PlantnetDataBundle:Plantunit')
            ->findOneBy(array(
                'module.id'=>$module->getId(),
                'id'=>$id
            ));
        if(!$plantunit){
            throw $this->createNotFoundException('Unable to find Plantunit entity.');
        }
        $others=$plantunit->getOthers();
        $tab_others_groups=array();
        if(count($others)){
            foreach($others as $other){
                if(!in_array($other->getModule()->getId(),array_keys($tab_others_groups))){
                    $order=array();
                    $field=$other->getModule()->getProperties();
                    foreach($field as $row){
                        if($row->getSortorder()){
                            $order[$row->getSortorder()]=$row->getId();
                        }
                    }
                    ksort($order);
                    $others_sorted=$dm->createQueryBuilder('PlantnetDataBundle:Other')
                        ->field('plantunit')->references($plantunit)
                        ->field('module')->references($other->getModule());
                    if(count($order)){
                        foreach($order as $num=>$prop){
                            $others_sorted->sort('property.'.$prop,'asc');
                        }
                    }
                    $others_sorted=$others_sorted->getQuery()->execute();
                    $tab_others_groups[$other->getModule()->getId()]=array(
                        $other->getModule(),
                        $others_sorted
                    );
                }
            }
        }
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
        return $this->render('PlantnetDataBundle:Frontend\Plantunit:details.html.twig',array(
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'plantunit'=>$plantunit,
            'display'=>$display,
            'layers'=>$layers,
            'tab_others_groups'=>$tab_others_groups,
            'current'=>'collection'
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/details_gallery/{id}",
     *      defaults={"page"=0},
     *      name="front_details_gallery"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/details_gallery/{id}/page{page}",
     *      requirements={"page"="\d+"},
     *      name="front_details_gallery_paginated"
     *  )
     * @Template()
     */
    public function details_galleryAction($project,$collection,$module,$id,$page)
    {
        $max_per_page=9;
        $start=$page*$max_per_page;
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('url'=>$module,'collection.id'=>$collection->getId()));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getDetails()==true){
                $display[]=$row->getId();
            }
        }
        $plantunit=$dm->getRepository('PlantnetDataBundle:Plantunit')
            ->findOneBy(array(
                'module.id'=>$module->getId(),
                'id'=>$id
            ));
        if(!$plantunit){
            throw $this->createNotFoundException('Unable to find Plantunit entity.');
        }
        $images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
            ->field('plantunit.id')->equals($plantunit->getId())
            ->sort('module.id','asc')
            ->limit($max_per_page)
            ->skip($start)
            ->getQuery()
            ->execute();
        $next=$page+1;
        if($start+$max_per_page>=count($images)){
            $next=-1;
        }
        return $this->render('PlantnetDataBundle:Frontend\Plantunit:details_gallery.html.twig',array(
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'plantunit'=>$plantunit,
            'images'=>$images,
            'next'=>$next
        ));
    }

    /**
     * @Route("/project/{project}/credits", name="front_credits")
     * @Template()
     */
    public function creditsAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $page=$dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'name'=>'credits'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        return $this->render('PlantnetDataBundle:Frontend\Pages:credits.html.twig',array(
            'project'=>$project,
            'page'=>$page,
            'current'=>'credits'
        ));
    }

    /**
     * @Route("/project/{project}/mentions", name="front_mentions")
     * @Template()
     */
    public function mentionsAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $page = $dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'name'=>'mentions'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        return $this->render('PlantnetDataBundle:Frontend\Pages:mentions.html.twig',array(
            'project'=>$project,
            'page'=>$page,
            'current'=>'mentions'
        ));
    }

    /**
     * @Route("/project/{project}/contacts", name="front_contacts")
     * @Template()
     */
    public function contactsAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $page = $dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'name'=>'contacts'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        return $this->render('PlantnetDataBundle:Frontend\Pages:contacts.html.twig',array(
            'project'=>$project,
            'page'=>$page,
            'current'=>'contacts'
        ));
    }
}