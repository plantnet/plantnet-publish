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
    function mylog($data,$data2=null,$data3=null){
        if( $data != null){
            $this->get('ladybug')->log(func_get_args());
        }
    }

    private function get_prefix()
    {
        return $this->container->getParameter('mdb_base').'_';
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            ),
            $this,
            $this->container->getParameter('mdb_base')
        );
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')->findAll();
        $page=$dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'alias'=>'home'
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        //
        $images=array();
        $imagesurl=array();
        $coll=null;

        // pour le chemin de la mignature de image distante
        $imgurl_coll = null;
        $imgurl_mod = null;
        $imgurl_ssmod = null;

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

                        if(!$child->getDeleting()&&$child->getType()=='imageurl'){

                            $skip=rand(0,($child->getNbrows()-1-$limit));
                            $tmp_imagesurl=$dm->createQueryBuilder('PlantnetDataBundle:Imageurl')
                                ->field('module')->references($child)
                                ->sort('_id','asc')
                                ->limit($limit)
                                ->skip($skip)
                                ->getQuery()
                                ->execute();

                            foreach($tmp_imagesurl as $imgurl){
                                if(!isset($imagesurl[$module->getId()])){
                                    $imagesurl[$module->getId()]=array();
                                }
                                $imagesurl[$module->getId()][]=$imgurl;
                            }
                            $imgurl_coll = $coll->getName();
                            $imgurl_mod = $module->getName();
                            $imgurl_ssmod = $child->getName();
                        }
                    }
                }
            }
        }
        $page->setContent(ControllerHelp::glossarize($dm,$coll,$page->getContent()));
        //
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();

        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').':project.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'page'=>$page,
            'collections'=>$collections,
            'images'=>$images,
            'imagesurl'=>$imagesurl,
            'translations'=>$translations,
            'current'=>'project',
            'imgurl_coll'=>$imgurl_coll,
            'imgurl_mod'=>$imgurl_mod,
            'imgurl_ssmod'=>$imgurl_ssmod
        ));
    }

    public function collection_listAction($project,$selected=null)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Collection:collection_list.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collections'=>$collections,
            'selected'=>$selected
        ));
    }

    /**
     * @Route("/project/{project}/collection/{collection}", name="front_collection")
     * @Template()
     */
    public function collectionAction($project,$collection)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project,
                'collection'=>$collection
            ),
            $this,
            $this->container->getParameter('mdb_base')
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
        // pour images distantes
        $imgurl_coll = null ;
        $imgurl_mod = null ;
        $imgurl_ssmod = null ;
        $imagesurl = array();

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
                    }elseif(!$child->getDeleting()&&$child->getType()=='imageurl'){
                        $limit=10;
                        $skip=rand(0,($child->getNbrows()-1-$limit));
                        if($skip>0){
                            $tmp_imagesurl=$dm->createQueryBuilder('PlantnetDataBundle:Imageurl')
                                ->field('module')->references($child)
                                ->sort('_id','asc')
                                ->limit($limit)
                                ->skip($skip)
                                ->getQuery()
                                ->execute();
                            foreach($tmp_imagesurl as $imgurl){
                                if(!isset($imagesurl[$module->getId()])){
                                    $imagesurl[$module->getId()]=array();
                                }
                                $imagesurl[$module->getId()][]=$imgurl;
                            }
                            $imgurl_coll = $collection->getName();
                            $imgurl_mod = $module->getName();
                            $imgurl_ssmod = $child->getName();
                        }
                    }
                }
            }
        }
        //
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Collection:collection.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'images'=>$images,
            'imagesurl'=>$imagesurl,
            'translations'=>$translations,
            'current'=>'collection',
            'imgurl_coll'=>$imgurl_coll,
            'imgurl_mod'=>$imgurl_mod,
            'imgurl_ssmod'=>$imgurl_ssmod
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        $translations=ControllerHelp::make_translations(
            $project,
            'front_module',
            array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module
            ),
            $this,
            $this->container->getParameter('mdb_base')
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
        if(!$module||$module->getType()!='text'||$module->getWsonly()==true){
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
        $config=ControllerHelp::get_config($project,$dm,$this);
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
            'current'=>'collection',
            'selected'=>'module'.$collection->getId().$module->getId()
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        $translations=ControllerHelp::make_translations(
            $project,
            'front_submodule',
            array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
                'submodule'=>$submodule
            ),
            $this,
            $this->container->getParameter('mdb_base')
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
        if(!$module||$module->getType()=='text'||$module->getWsonly()==true){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display=array();
        $field=$module_parent->getProperties();
        foreach($field as $row){
            if($row->getMain()==true){
                $display[]=$row->getId();
            }
        }

        $modgettype = $module->getType() ;

        switch($module->getType()){
            case 'image':case 'imageurl':
                if($module->getType() == 'image') {
                    $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Image')
                        ->field('module')->references($module)
                        ->sort('title1', 'asc')
                        ->sort('title2', 'asc');
                }else{
                    $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Imageurl')
                        ->field('module')->references($module)
                        ->sort('title1', 'asc')
                        ->sort('title2', 'asc');
                }
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
                $config=ControllerHelp::get_config($project,$dm,$this);
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
                    'selected'=>'submodule'.$collection->getId().$module_parent->getId().$module->getid()
                ));
                break;
            case 'locality':
                $locations=array();
                $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                $config=ControllerHelp::get_config($project,$dm,$this);
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
                    'current'=>'collection',
                    'selected'=>'submodule'.$collection->getId().$module_parent->getId().$module->getid()
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        if(!$module||$module->getType()!='locality'||$module->getWsonly()==true){
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
        $link_pattern=$this->get('router')->generate('front_details',array(
            'project'=>$project,
            'collection'=>$collection->getUrl(),
            'module'=>$module_parent->getUrl(),
            'id'=>0
        ));
        $link_pattern=substr($link_pattern,0,-1);
        foreach($c_locations as $id=>$l){
            $loc=array(
                'type'=>'Feature',
                'id'=>$id,
                'geometry'=>array(
                    'type'=>'Point',
                    'coordinates'=>array(
                        $l['longitude'],
                        $l['latitude']
                    )
                ),
                'properties'=>array(
                    'punit'=>$link_pattern.$l['plantunit']['$id']->{'$id'},
                    'title1'=>$l['title1'],
                    'title2'=>$l['title2'],
                    'title3'=>$l['title3'],
                    'loc_data'=>''
                )
            );
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
                'id'=>$id
            ),
            $this,
            $this->container->getParameter('mdb_base')
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
        if(!$module||$module->getWsonly()==true){
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
        $highlights=array();
        $others=$plantunit->getOthers();
        $tab_others_groups=array();
        if(count($others)){
            foreach($others as $other){
                $hl_colums=array();
                $field=$other->getModule()->getProperties();
                foreach($field as $row){
                    if($row->getVernacular()==true){
                        $hl_colums[$row->getId()]=$row->getName();
                    }
                }
                if(count($hl_colums)){
                    $prop=$other->getProperty();
                    foreach($prop as $key=>$val){
                        if(array_key_exists($key,$hl_colums)){
                            if(!isset($highlights[$other->getModule()->getId()])){
                                $highlights[$other->getModule()->getId()]=array();
                            }
                            if(!isset($highlights[$other->getModule()->getId()][$key])){
                                $highlights[$other->getModule()->getId()][$key]=array(
                                    'name'=>$hl_colums[$key],
                                    'horizontal'=>true,
                                    'values'=>array()
                                );
                            }
                            if(strlen($val)>25){
                                $highlights[$other->getModule()->getId()][$key]['horizontal']=false;
                            }
                            $highlights[$other->getModule()->getId()][$key]['values'][]=$val;
                        }
                    }
                }
                if(!in_array($other->getModule()->getId(),array_keys($tab_others_groups))){
                    $order=array();
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
            foreach($highlights as $id_mod=>$tab_mod){
                foreach($tab_mod as $id_col=>$tab_col){
                    $tmp_tab=$highlights[$id_mod][$id_col]['values'];
                    natcasesort($tmp_tab);
                    $highlights[$id_mod][$id_col]['values']=$tmp_tab;
                }
            }
        }
        usort($tab_others_groups,function($a,$b){
            return ($a[0]->getName()<$b[0]->getName())?-1:1;
        });
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
        $config=ControllerHelp::get_config($project,$dm,$this);
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
            'highlights'=>$highlights,
            'translations'=>$translations,
            'current'=>'collection',
            'selected'=>'module'.$collection->getId().$module->getId()
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        if(!$module||$module->getWsonly()==true){
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
        $imagesurl=$dm->createQueryBuilder('PlantnetDataBundle:Imageurl')
            ->field('plantunit.id')->equals($plantunit->getId())
            ->sort('module.id','asc')
            ->limit($max_per_page)
            ->skip($start)
            ->getQuery()
            ->execute();
        $nexturl=$page+1;
        if($start+$max_per_page>=count($imagesurl)){
            $nexturl=-1;
        }
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Plantunit:details_gallery.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'plantunit'=>$plantunit,
            'images'=>$images,
            'next'=>$next,
            'imagesurl'=>$imagesurl,
            'nexturl'=>$nexturl
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/glossary/terms",
     *      defaults={"page"=1},
     *      name="front_glossary"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/glossary/terms/page{page}",
     *      requirements={"page"="\d+"},
     *      name="front_glossary_paginated"
     *  )
     * @Method("get")
     * @Template()
     */
    public function glossaryAction($project,$collection,$page)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        if($this->container->get('request')->get('_route')=='front_glossary_paginated'&&$page==1){
            return $this->redirect($this->generateUrl('front_glossary',array(
                'project'=>$project,
                'collection'=>$collection
                )
            ),301);
        }
        $translations=ControllerHelp::make_translations(
            $project,
            'front_glossary',
            array(
                'project'=>$project,
                'collection'=>$collection
            ),
            $this,
            $this->container->getParameter('mdb_base')
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
        $definitions=$dm->createQueryBuilder('PlantnetDataBundle:Definition')
            ->field('glossary')->references($glossary)
            ->field('parent')->equals(null)
            ->sort('displayedname','asc');
        $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($definitions));
        try{
            $paginator->setMaxPerPage(50);
            $paginator->setCurrentPage($page);
        }
        catch(\Pagerfanta\Exception\NotValidCurrentPageException $e){
            throw $this->createNotFoundException('Page not found.');
        }
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').':glossary.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'glossary'=>$glossary,
            'paginator'=>$paginator,
            'translations'=>$translations,
            'current'=>'collection',
            'selected'=>'glossary'.$collection->getId()
        ));
    }

    /**
     * @Route("/project/{project}/credits", name="front_credits")
     * @Template()
     */
    public function creditsAction($project)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            ),
            $this,
            $this->container->getParameter('mdb_base')
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
        $config=ControllerHelp::get_config($project,$dm,$this);
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            ),
            $this,
            $this->container->getParameter('mdb_base')
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
        $config=ControllerHelp::get_config($project,$dm,$this);
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            ),
            $this,
            $this->container->getParameter('mdb_base')
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
        $config=ControllerHelp::get_config($project,$dm,$this);
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            ),
            $this,
            $this->container->getParameter('mdb_base')
        );
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Pages:sitemap.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collections'=>$collections,
            'translations'=>$translations,
            'current'=>'sitemap'
        ));
    }

    /**
     * @Route("/project/{project}/search", name="front_search")
     * @Method("get")
     * @Template()
     */
    public function searchAction($project,Request $request)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            $this->container->get('request')->get('_route'),
            array(
                'project'=>$project
            ),
            $this,
            $this->container->getParameter('mdb_base')
        );
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $punits=array();
        $query='';
        $paginator=null;
        if($request->isMethod('GET')&&$request->query->get('q')){
            $query=$request->query->get('q');
            $string=new \MongoRegex('/.*'.StringHelp::accentToRegex($query).'.*/i');
            $collections=$dm->getRepository('PlantnetDataBundle:Collection')
                ->findAll();
            //
            $punit_filters=array(
                'identifier'=>1,
                'title1'=>1,
                'title2'=>1,
                'title3'=>1
            );
            $img_filters=array();
            $imgurl_filters=array();
            $loc_filters=array();
            $other_filters=array();
            //
            /*
            foreach($collections as $collection){
                $modules=$collection->getModules();
                foreach($modules as $module){
                    $fields=$module->getProperties();
                    foreach($fields as $field){
                        if($field->getSearch()==true){
                            $punit_filters['attributes.'.$field->getId()]=1;
                        }
                    }
                    $submodules=$module->getChildren();
                    foreach($submodules as $submodule){
                        switch($submodule->getType()){
                            case 'image':
                                break;
                            case 'locality':
                                break;
                            case 'other':
                                $subfields=$submodule->getProperties();
                                foreach($subfields as $subfield){
                                    if($subfield->getSearch()==true){
                                        $other_filters['property.'.$subfield->getId()]=1;
                                    }
                                }
                                break;
                        }
                    }
                }
            }
            */
            //
            $tmp_ids=array();
            if(count($img_filters)){
                $ids=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->hydrate(false)
                    ->select('plantunit');
                foreach($img_filters as $filter=>$active){
                    $ids->addOr($ids->expr()->field($filter)->in(array($string)));
                }
                $ids=$ids->getQuery()
                    ->execute();
                foreach($ids as $id){
                    $tmp_ids[]=$id['plantunit']['$id'].'';
                }
            }
            if(count($imgurl_filters)){
                $ids=$dm->createQueryBuilder('PlantnetDataBundle:Imageurl')
                    ->hydrate(false)
                    ->select('plantunit');
                foreach($img_filters as $filter=>$active){
                    $ids->addOr($ids->expr()->field($filter)->in(array($string)));
                }
                $ids=$ids->getQuery()
                    ->execute();
                foreach($ids as $id){
                    $tmp_ids[]=$id['plantunit']['$id'].'';
                }
            }
            if(count($loc_filters)){
                $ids=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                    ->hydrate(false)
                    ->select('plantunit');
                foreach($loc_filters as $filter=>$active){
                    $ids->addOr($ids->expr()->field($filter)->in(array($string)));
                }
                $ids=$ids->getQuery()
                    ->execute();
                foreach($ids as $id){
                    $tmp_ids[]=$id['plantunit']['$id'].'';
                }
            }
            if(count($other_filters)){
                $ids=$dm->createQueryBuilder('PlantnetDataBundle:Other')
                    ->hydrate(false)
                    ->select('plantunit');
                foreach($other_filters as $filter=>$active){
                    $ids->addOr($ids->expr()->field($filter)->in(array($string)));
                }
                $ids=$ids->getQuery()
                    ->execute();
                foreach($ids as $id){
                    $tmp_ids[]=$id['plantunit']['$id'].'';
                }
            }
            $tmp_ids=array_unique($tmp_ids);
            //
            $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit');
            foreach($punit_filters as $filter=>$active){
                $punits->addOr($punits->expr()->field($filter)->in(array($string)));
            }
            if(count($tmp_ids)){
                $punits->addOr($punits->expr()->field('_id')->in($tmp_ids));
            }
            $punits=$punits
                ->sort('title1','asc')
                ->sort('title2','asc')
                ->sort('title3','asc');
            $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($punits));
            try{
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
            }
            catch(\Pagerfanta\Exception\NotValidCurrentPageException $e){
                throw $this->createNotFoundException('Page not found.');
            }
            if($paginator->getNbResults()==1){
                foreach($paginator as $punit){
                    if($punit->getModule()->getWsonly()!=true){
                        return $this->redirect($this->generateUrl('front_details',array(
                            'project'=>$project,
                            'collection'=>$punit->getModule()->getCollection()->getUrl(),
                            'module'=>$punit->getModule()->getUrl(),
                            'id'=>$punit->getIdentifier()
                            )
                        ));
                    }
                    
                }
            }
        }
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').':search.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'query'=>$query,
            'paginator'=>$paginator,
            'translations'=>$translations,
            'current'=>'contacts'
        ));
    }
}