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
use Plantnet\DataBundle\Utils\ControllerHelp;


/**
 * Default controller.
 *
 * @Route("")
 */
class DataController extends Controller
{
    private function check_enable_project($project)
    {
        $prefix=substr($this->get_prefix(),0,-1);
        $connection=new \MongoClient();
        $db=$connection->$prefix->Database->findOne(array(
            'link'=>$project
        ),array(
            'enable'=>1
        ));
        if($db){
            if(isset($db['enable'])&&$db['enable']===false){
                throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
            }
        }
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
    }
    
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
        return $this->container->getParameter('mdb_base').'_';
    }

    private function get_config($project)
    {
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $this->createNotFoundException('Unable to find Config entity.');
        }
        $default=$config->getDefaultlanguage();
        if(!empty($default)){
            $this->getRequest()->setLocale($default);
        }
        return $config;
    }

    private function make_translations($project,$route,$params)
    {
        $tab_links=array();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->container->getParameter('mdb_base'));
        $database=$dm->createQueryBuilder('PlantnetDataBundle:Database')
            ->field('link')->equals($project)
            ->getQuery()
            ->getSingleResult();
        if(!$database){
            throw $this->createNotFoundException('Unable to find Database entity.');
        }
        $current=$database->getlanguage();
        $parent=$database->getParent();
        if($parent){
            $database=$parent;
        }
        $children=$database->getChildren();
        if(count($children)){
            $params['project']=$database->getLink();
            $tab_links[$database->getLanguage()]=array(
                'lang'=>$database->getLanguage(),
                'language'=>\Locale::getDisplayName($database->getLanguage(),$database->getLanguage()),
                'link'=>$this->get('router')->generate($route,$params,true),
                'active'=>($database->getLanguage()==$current)?1:0
            );
            $tab_sub_links=array();
            foreach($children as $child){
                if($child->getEnable()==true){
                    $params['project']=$child->getLink();
                    $tab_sub_links[$child->getLanguage()]=array(
                        'lang'=>$child->getLanguage(),
                        'language'=>\Locale::getDisplayName($child->getLanguage(),$child->getLanguage()),
                        'link'=>$this->get('router')->generate($route,$params,true),
                        'active'=>($child->getLanguage()==$current)?1:0
                    );
                }
            }
            if(count($tab_sub_links)){
                ksort($tab_sub_links);
                $tab_links=array_merge($tab_links,$tab_sub_links);
            }
            else{
                $tab_links=array();
            }
        }
        return $tab_links;
    }

    /**
     * @Route("/", name="front_index")
     * @Template()
     */
    public function indexAction()
    {
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $projects=$dm->createQueryBuilder('PlantnetDataBundle:Database')
            ->field('parent')->equals(null)
            ->sort('name','asc')
            ->getQuery()
            ->execute();
        return $this->render('PlantnetDataBundle:Root:index.html.twig',array(
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
        $this->check_enable_project($project);
        $translations=$this->make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            )
        );
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        $page=$dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'alias'=>'home'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        //
        $images=array();
        $coll=null;
        foreach($collections as $collection){
            $collection->setDescription(ControllerHelp::glossarize($dm,$collection,$collection->getDescription()));
            $coll=$collection;
            $modules=$collection->getModules();
            foreach($modules as $module){
                $module->setDescription(ControllerHelp::glossarize($dm,$collection,$module->getDescription()));
                if(!$module->getDeleting()){
                    $children=$module->getChildren();
                    foreach($children as $child){
                        if(!$child->getDeleting()&&$child->getType()=='image'){
                            $limit=10;
                            $skip=rand(0,($child->getNbrows()-1-$limit));
                            if($skip>0){
                                $tmp_images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                                    ->field('module')->references($child)
                                    ->sort('_id','asc')
                                    ->limit($limit)
                                    ->skip($skip)
                                    ->getQuery()
                                    ->execute();
                                foreach($tmp_images as $img){
                                    if(!isset($images[$module->getId()])){
                                        $images[$module->getId()]=array();
                                    }
                                    $images[$module->getId()][]=$img;
                                }
                            }
                        }
                    }
                }
            }
        }
        $page->setContent(ControllerHelp::glossarize($dm,$coll,$page->getContent()));
        //
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').':project.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'page'=>$page,
            'collections'=>$collections,
            'images'=>$images,
            'translations'=>$translations,
            'current'=>'project'
        ));
    }

    public function collection_listAction($project)
    {
        $this->check_enable_project($project);
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Collection:collection_list.html.twig',array(
            'config'=>$config,
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
        $this->check_enable_project($project);
        $translations=$this->make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project,
                'collection'=>$collection
            )
        );
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        //
        $images=array();
        $collection->setDescription(ControllerHelp::glossarize($dm,$collection,$collection->getDescription()));
        $modules=$collection->getModules();
        foreach($modules as $module){
            $module->setDescription(ControllerHelp::glossarize($dm,$collection,$module->getDescription()));
            if(!$module->getDeleting()){
                $children=$module->getChildren();
                foreach($children as $child){
                    if(!$child->getDeleting()&&$child->getType()=='image'){
                        $limit=10;
                        $skip=rand(0,($child->getNbrows()-1-$limit));
                        if($skip>0){
                            $tmp_images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                                ->field('module')->references($child)
                                ->sort('_id','asc')
                                ->limit($limit)
                                ->skip($skip)
                                ->getQuery()
                                ->execute();
                            foreach($tmp_images as $img){
                                if(!isset($images[$module->getId()])){
                                    $images[$module->getId()]=array();
                                }
                                $images[$module->getId()][]=$img;
                            }
                        }
                    }
                }
            }
        }
        //
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Collection:collection.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'images'=>$images,
            'translations'=>$translations,
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
        $this->check_enable_project($project);
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
        //
        $translations=$this->make_translations(
            $project,
            'front_module',
            array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module
            )
        );
        //
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
        //
        $module->setDescription(ControllerHelp::glossarize($dm,$collection,$module->getDescription()));
        //
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
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:datagrid.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'paginator'=>$paginator,
            'display'=>$display,
            'page'=>$page,
            'sortby'=>$sortby,
            'sortorder'=>$sortorder,
            'translations'=>$translations,
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
        $this->check_enable_project($project);
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
        //
        $translations=$this->make_translations(
            $project,
            'front_submodule',
            array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
                'submodule'=>$submodule
            )
        );
        //
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
                $config=$this->get_config($project);
                $tpl=$config->getTemplate();
                return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:gallery.html.twig',array(
                    'config'=>$config,
                    'project'=>$project,
                    'collection'=>$collection,
                    'module_parent'=>$module_parent,
                    'module'=>$module,
                    'paginator'=>$paginator,
                    'display'=>$display,
                    'translations'=>$translations,
                    'current'=>'collection',
                ));
                break;
            case 'locality':
                $locations=array();
                $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                $config=$this->get_config($project);
                $tpl=$config->getTemplate();
                return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:map.html.twig',array(
                    'config'=>$config,
                    'project'=>$project,
                    'collection'=>$collection,
                    'module'=>$module,
                    'module_parent'=>$module_parent,
                    'layers'=>$layers,
                    'locations'=>$locations,
                    'translations'=>$translations,
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
        $this->check_enable_project($project);
        $max_per_page=5000;
        $start=$page*$max_per_page;
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
                'title2'=>1,
                'title3'=>1
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
                'title3'=>'',
                'loc_data'=>''
            );
            if(isset($l['title3'])&&!empty($l['title3'])){
                $loc['properties']['title3']=$l['title3'];
            }
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
        $this->check_enable_project($project);
        $translations=$this->make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
                'id'=>$id
            )
        );
        //
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
        //check for old links
        $check_plantunit=$dm->getRepository('PlantnetDataBundle:Plantunit')
            ->findOneBy(array(
                'module.id'=>$module->getId(),
                'id'=>$id
            ));
        if($check_plantunit){
            return $this->redirect($this->generateUrl('front_details',array(
                'project'=>$project,
                'collection'=>$collection->getUrl(),
                'module'=>$module->getUrl(),
                'id'=>$check_plantunit->getIdentifier()
                )
            ),301);
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
                'identifier'=>$id
            ));
        if(!$plantunit){
            throw $this->createNotFoundException('Unable to find Plantunit entity.');
        }
        //
        $attributes=$plantunit->getAttributes();
        foreach($attributes as $key=>$attribute){
            $attributes[$key]=ControllerHelp::glossarize($dm,$collection,$attribute,true);
        }
        $plantunit->setAttributes($attributes);
        //
        $vernaculars=array();
        $others=$plantunit->getOthers();
        $tab_others_groups=array();
        if(count($others)){
            foreach($others as $other){
                $verac_columns=array();
                $field=$other->getModule()->getProperties();
                foreach($field as $row){
                    if($row->getVernacular()==true){
                        $verac_columns[]=$row->getId();
                    }
                }
                if(count($verac_columns)){
                    $prop=$other->getProperty();
                    foreach($prop as $key=>$val){
                        if(in_array($key,$verac_columns)&&!in_array($val,$vernaculars)){
                            $vernaculars[]=$val;
                        }
                    }
                }
                if(!in_array($other->getModule()->getId(),array_keys($tab_others_groups))){
                    $order=array();
                    // $field=$other->getModule()->getProperties();
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
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Plantunit:details.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'plantunit'=>$plantunit,
            'display'=>$display,
            'layers'=>$layers,
            'tab_others_groups'=>$tab_others_groups,
            'vernaculars'=>$vernaculars,
            'translations'=>$translations,
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
        $this->check_enable_project($project);
        $max_per_page=9;
        $start=$page*$max_per_page;
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
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Plantunit:details_gallery.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'plantunit'=>$plantunit,
            'images'=>$images,
            'next'=>$next
        ));
    }

    /**
     * @Route("/project/{project}/collection/{collection}/glossary/terms", name="front_glossary")
     * @Template()
     */
    public function glossaryAction($project,$collection)
    {
        $this->check_enable_project($project);
        $translations=$this->make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project,
                'collection'=>$collection
            )
        );
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $glossary=$collection->getGlossary();
        if(!$glossary){
            throw $this->createNotFoundException('Unable to find Glossary entity.');
        }
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').':glossary.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'glossary'=>$glossary,
            'translations'=>$translations,
            'current'=>'collection'
        ));
    }

    /**
     * @Route("/project/{project}/credits", name="front_credits")
     * @Template()
     */
    public function creditsAction($project)
    {
        $this->check_enable_project($project);
        $translations=$this->make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            )
        );
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $page=$dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'alias'=>'credits'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Pages:credits.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'page'=>$page,
            'translations'=>$translations,
            'current'=>'credits'
        ));
    }

    /**
     * @Route("/project/{project}/mentions", name="front_mentions")
     * @Template()
     */
    public function mentionsAction($project)
    {
        $this->check_enable_project($project);
        $translations=$this->make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            )
        );
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $page = $dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'alias'=>'mentions'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Pages:mentions.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'page'=>$page,
            'translations'=>$translations,
            'current'=>'mentions'
        ));
    }

    /**
     * @Route("/project/{project}/contacts", name="front_contacts")
     * @Template()
     */
    public function contactsAction($project)
    {
        $this->check_enable_project($project);
        $translations=$this->make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            )
        );
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $page = $dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'alias'=>'contacts'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Pages:contacts.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'page'=>$page,
            'translations'=>$translations,
            'current'=>'contacts'
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/glossary/term",
     *      defaults={"term"="null"},
     *      name="front_glossary_query_path"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/glossary/term/{term}",
     *      name="front_glossary_query"
     *  )
     * @Template()
     */
    public function glossary_queryAction($project,$collection,$term)
    {
        $this->check_enable_project($project);
        if($term=='null'){
            $response=new Response(json_encode(array()));
            $response->headers->set('Content-Type','application/json');
            return $response;
            exit;
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $glossary=$collection->getGlossary();
        if(!$glossary){
            throw $this->createNotFoundException('Unable to find Glossary entity.');
        }
        $definition=$dm->createQueryBuilder('PlantnetDataBundle:Definition')
            ->hydrate(false)
            ->select('definition','path')
            ->field('glossary')->references($glossary)
            ->field('name')->equals($term)
            ->getQuery()
            ->getSingleResult();
        $result=array(
            'definition'=>'',
            'path'=>'',
            'dir'=>''
        );
        if($definition){
            $result['definition']=StringHelp::truncate($definition['definition'],200);
            $result['path']=$definition['path'];
            $result['dir']=$glossary->getUploaddir();
        }
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
        exit;
    }

    /**
     * @Route("/project/{project}/sitemap", name="front_sitemap")
     * @Template()
     */
    public function sitemapAction($project)
    {
        $this->check_enable_project($project);
        $translations=$this->make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            )
        );
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        $page=$dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'alias'=>'home'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Pages:sitemap.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'page'=>$page,
            'collections'=>$collections,
            'translations'=>$translations,
            'current'=>'contacts'
        ));
    }
}