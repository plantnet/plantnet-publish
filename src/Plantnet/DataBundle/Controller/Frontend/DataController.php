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
        return $this->render('PlantnetDataBundle:Frontend:index.html.twig',array(
            'projects'=>$projects,
            'current'=>'index'
        ));
    }

    /**
     * @Route("/project/{project}", name="_project")
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
     * @Route("/project/{project}/collection/{collection}", name="_collection")
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
            ->findOneByName($collection);
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
     * @Route("/project/{project}/collection/{collection}/{module}", defaults={"page"=1}, name="_module")
     * @Route("/project/{project}/collection/{collection}/{module}/page{page}", requirements={"page"="\d+"}, name="_module_paginated")
     * @Method("get")
     * @Template()
     */
    public function moduleAction($project,$collection,$module,$page,Request $request)
    {
        $form_page=$request->query->get('form_page');
        if(!empty($form_page)){
            $page=$form_page;
        }
        if($this->container->get('request')->get('_route')=='_module_paginated'&&$page==1){
            return $this->redirect($this->generateUrl('_module',array(
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
            ->findOneByName($collection);
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=>$module,
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
            'current'=>'collection'
        ));
    }

    /**
     * @Route("/project/{project}/collection/{collection}/{module}/module/{submodule}", defaults={"page"=1}, name="_submodule")
     * @Route("/project/{project}/collection/{collection}/{module}/module/{submodule}/page{page}", requirements={"page"="\d+"}, name="_submodule_paginated")
     * @Method("get")
     * @Template()
     */
    public function submoduleAction($project,$collection,$module,$submodule,$page,Request $request)
    {
        $form_page=$request->query->get('form_page');
        if(!empty($form_page)){
            $page=$form_page;
        }
        if($this->container->get('request')->get('_route')=='_submodule_paginated'&&$page==1){
            return $this->redirect($this->generateUrl('_submodule',array(
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
            ->findOneByName($collection);
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module_parent=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module_parent){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=>$submodule,
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
        switch($module->getType())
        {
            case 'image':
                $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->field('module')->references($module);
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
                // $db=$this->get_prefix().$project;
                // $m=new \Mongo();
                // $plantunits=array();
                // $c_plantunits=$m->$db->Plantunit->find(
                //     array('module.$id'=>new \MongoId($module_parent->getId())),
                //     array('_id'=>1,'title1'=>1,'title2'=>1)
                // );
                // foreach($c_plantunits as $id=>$p)
                // {
                //     $plant=array();
                //     $plant['title1']=$p['title1'];
                //     $plant['title2']=$p['title2'];
                //     $plantunits[$id]=$plant;
                // }
                // unset($c_plantunits);
                $locations=array();
                // $c_locations=$m->$db->Location->find(
                //     array(
                //         'module.$id'=>new \MongoId($module->getId())
                //     ),
                //     array('_id'=>1,'latitude'=>1,'longitude'=>1,'plantunit.$id'=>1)
                // );
                // foreach($c_locations as $id=>$l)
                // {
                //     $loc=array();
                //     $loc['id']=$id;
                //     $loc['latitude']=$l['latitude'];
                //     $loc['longitude']=$l['longitude'];
                //     $loc['title1']='';
                //     $loc['title2']='';
                //     if(array_key_exists($l['plantunit']['$id']->{'$id'},$plantunits))
                //     {
                //         if(isset($plantunits[$l['plantunit']['$id']->{'$id'}]['title1']))
                //         {
                //             $loc['title1']=$plantunits[$l['plantunit']['$id']->{'$id'}]['title1'];
                //         }
                //         if(isset($plantunits[$l['plantunit']['$id']->{'$id'}]['title2']))
                //         {
                //             $loc['title2']=$plantunits[$l['plantunit']['$id']->{'$id'}]['title2'];
                //         }
                //     }
                //     $locations[]=$loc;
                // }
                // unset($plantunits);
                // unset($c_locations);
                // unset($m);
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
     * @Route("/project/{project}/collection/{collection}/{module}/module/{submodule}/datamap", defaults={"page"=0}, name="_datamap")
     * @Route("/project/{project}/collection/{collection}/{module}/module/{submodule}/datamap/page{page}", requirements={"page"="\d+"}, name="_datamap_paginated")
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
            ->findOneByName($collection);
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module_parent=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module_parent){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=>$submodule,
                'parent.id'=>$module_parent->getId(),
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='locality'){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        //data extract
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
        )->sort(array('_id'=>1))->limit($max_per_page)->skip($start);
        foreach($c_locations as $id=>$l)
        {
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
                'title1'=>'',
                'title2'=>''
            );
            if(array_key_exists($l['plantunit']['$id']->{'$id'},$plantunits))
            {
                $loc['properties']['punit']=$this->get('router')->generate('_details',array(
                    'project'=>$project,
                    'collection'=>$collection->getName(),
                    'module'=>$module_parent->getName(),
                    'id'=>$l['plantunit']['$id']->{'$id'}
                ));
                if(isset($plantunits[$l['plantunit']['$id']->{'$id'}]['title1']))
                {
                    $loc['properties']['title1']=$plantunits[$l['plantunit']['$id']->{'$id'}]['title1'];
                }
                if(isset($plantunits[$l['plantunit']['$id']->{'$id'}]['title2']))
                {
                    $loc['properties']['title2']=$plantunits[$l['plantunit']['$id']->{'$id'}]['title2'];
                }
            }
            $locations[]=$loc;
        }
        unset($plantunits);
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
     * @Route("/project/{project}/collection/{collection}/{module}/details/{id}", name="_details")
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
            ->findOneByName($collection);
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=>$module,
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
        if(count($others))
        {
            foreach($others as $other)
            {
                if(!in_array($other->getModule()->getId(),array_keys($tab_others_groups)))
                {
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
     * @Route("/project/{project}/collection/{collection}/{module}/details_gallery/{id}", defaults={"page"=0}, name="_details_gallery")
     * @Route("/project/{project}/collection/{collection}/{module}/details_gallery/{id}/page{page}", requirements={"page"="\d+"}, name="_details_gallery_paginated")
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
            ->findOneByName($collection);
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name'=>$module,'collection.id'=>$collection->getId()));
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
            ->sort('id','asc')
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

    private function createModuleSearchForm($fields,$module)
    {
        $properties=$module->getProperties();
        $tab_prop=array();
        foreach($properties as $prop)
        {
            $tab_prop[$prop->getId()]=$prop;
        }
        unset($properties);
        $defaults=null;
        $constraints=array(
            'y_lat_1_bottom_left'=>new TypeConstraint('float'),
            'x_lng_1_bottom_left'=>new TypeConstraint('float'),
            'y_lat_2_top_right'=>new TypeConstraint('float'),
            'x_lng_2_top_right'=>new TypeConstraint('float'),
        );
        $form=$this->createFormBuilder($defaults,array(
                'csrf_protection'=>false,
                'constraints'=>$constraints
            ))
            ->add('y_lat_1_bottom_left','hidden',array('required'=>false))
            ->add('x_lng_1_bottom_left','hidden',array('required'=>false))
            ->add('y_lat_2_top_right','hidden',array('required'=>false))
            ->add('x_lng_2_top_right','hidden',array('required'=>false));
        $field_num=0;
        foreach($fields as $field)
        {
            $prop=$tab_prop[$field];
            $form->add('field_'.$field_num,'text',array(
                'required'=>false,
                'label'=>$prop->getName(),
                'attr'=>array(
                    'class'=>'str str_'.$field_num
                )
            ));
            $form->add('name_field_'.$field_num,'hidden',array(
                'required'=>true,
                'data'=>$field
            ));
            $form->add('field_'.$field_num.'_string','hidden',array(
                'required'=>false,
                'label'=>false,
                'attr'=>array(
                    'class'=>'string_str_'.$field_num
                )
            ));
            $field_num++;
        }
        $form=$form->getForm();
        return $form;
    }

    /**
     * @Route("/project/{project}/collection/{collection}/{module}/search", name="_module_search")
     * @Template()
     */
    public function module_searchAction($project,$collection,$module)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        if($module->getParent()){
            $module=$dm->getRepository('PlantnetDataBundle:Module')
                ->find($module->getParent()->getId());
        }
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $fields=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getSearch()==true){
                $fields[]=$row->getId();
            }
        }
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers_search.xml',0,true);
        $form=$this->createModuleSearchForm($fields,$module);
        return $this->render('PlantnetDataBundle:Frontend\Module:module_search.html.twig',array(
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'layers'=>$layers,
            'form'=>$form->createView(),
            'current'=>'collection'
        ));
    }

    /**
     * @Route("/project/{project}/collection/{collection}/{module}/result", defaults={"mode"="grid"}, name="_module_result")
     * @Route("/project/{project}/collection/{collection}/{module}/result/{mode}", requirements={"mode"="\w+"}, name="_module_result_mode")
     * @Method("get")
     * @Template()
     */
    public function module_resultAction($project,$collection,$module,$mode,Request $request)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects)){
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        if($module->getParent()){
            $module=$dm->getRepository('PlantnetDataBundle:Module')
                ->find($module->getParent()->getId());
            if(!$module){
                throw $this->createNotFoundException('Unable to find Module entity.');
            }
        }
        $fields=array();
        $display=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getSearch()==true){
                $fields[] = $row->getId();
            }
            if($row->getMain()==true){
                $display[]=$row->getId();
            }
        }
        $form=$this->createModuleSearchForm($fields,$module);
        if($request->isMethod('GET'))
        {
            $form->bind($request);
            $data=$form->getData();
            $dm=$this->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
            $ids_punit=array();
            // Location Filters
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
            // Field Filters
            $fields=array();
            foreach($data as $key=>$val)
            {
                if(substr_count($key,'name_field_'))
                {
                    if(isset($data[str_replace('name_','',$key).'_string'])&&!empty($data[str_replace('name_','',$key).'_string']))
                    {
                        $fields[$val]=explode('~|~',$data[str_replace('name_','',$key).'_string']);
                    }
                    elseif(isset($data[str_replace('name_','',$key)])&&!empty($data[str_replace('name_','',$key)]))
                    {
                        $fields[$val]=$data[str_replace('name_','',$key)];
                    }
                }
            }
            // Filters to URL
            $url='';
            $data_url=$form->getData();
            foreach($data_url as $key=>$val)
            {
                if($url!=''){
                    $url.='&';
                }
                $url.=$form->getName().'['.$key.']='.$val;
            }
            // Search
            switch($mode)
            {
                case 'grid':
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
                                if(is_array($value))
                                {
                                    for($i=0;$i<count($value);$i++)
                                    {
                                        $value[$i]=new \MongoRegex('/.*'.$value[$i].'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value
                                    );
                                }
                                else
                                {
                                    $plantunits->field('attributes.'.$key)->in(
                                        array(
                                            new \MongoRegex('/.*'.$value.'.*/i')
                                        )
                                    );
                                }
                            }
                        }
                        $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($plantunits));
                        try{
                            $paginator->setMaxPerPage(50);
                            $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                        }
                        catch(\Pagerfanta\Exception\NotValidCurrentPageException $e){
                            throw $this->createNotFoundException('Page not found.');
                        }
                        $nbResults=$paginator->getNbResults();
                    }
                    return $this->render('PlantnetDataBundle:Frontend\Module:module_result.html.twig',array(
                        'project'=>$project,
                        'collection'=>$collection,
                        'module'=>$module,
                        'paginator'=>$paginator,
                        'display'=>$display,
                        'nbResults'=>$nbResults,
                        'url'=>$url,
                        'current'=>'collection',
                        'current_display'=>'grid'
                    ));
                    break;
                case 'images':
                    $paginator=null;
                    $nbResults=0;
                    if(count($ids_punit)||count($fields))
                    {
                        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('module.id')->equals($module->getId());
                        if(count($ids_punit))
                        {
                            $plantunits->field('_id')->in($ids_punit);
                        }
                        if(count($fields))
                        {
                            foreach($fields as $key=>$value)
                            {
                                if(is_array($value))
                                {
                                    for($i=0;$i<count($value);$i++)
                                    {
                                        $value[$i]=new \MongoRegex('/.*'.$value[$i].'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value
                                    );
                                }
                                else
                                {
                                    $plantunits->field('attributes.'.$key)->in(
                                        array(
                                            new \MongoRegex('/.*'.$value.'.*/i')
                                        )
                                    );
                                }
                            }
                        }
                        $ids_c=$plantunits
                            ->getQuery()
                            ->execute();
                        $ids_tab=array();
                        foreach($ids_c as $id)
                        {
                            $ids_tab[$id['_id']->{'$id'}]=$id['_id']->{'$id'};
                        }
                        unset($ids_c);
                        $images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                            ->field('plantunit.id')->in($ids_tab);
                        $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($images));
                        try{
                            $paginator->setMaxPerPage(15);
                            $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                        }
                        catch(\Pagerfanta\Exception\NotValidCurrentPageException $e){
                            throw $this->createNotFoundException('Page not found.');
                        }
                        $nbResults=$paginator->getNbResults();
                    }
                    return $this->render('PlantnetDataBundle:Frontend\Module:module_result.html.twig',array(
                        'project'=>$project,
                        'collection'=>$collection,
                        'module_parent'=>$module,
                        'module'=>$module,
                        'paginator'=>$paginator,
                        'display'=>$display,
                        'nbResults'=>$nbResults,
                        'url'=>$url,
                        'current'=>'collection',
                        'current_display'=>'images'
                    ));
                    break;
                case 'locations':
                    $nbResults=0;
                    if(count($ids_punit)||count($fields))
                    {
                        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('module.id')->equals($module->getId());
                        if(count($ids_punit))
                        {
                            $plantunits->field('_id')->in($ids_punit);
                        }
                        if(count($fields))
                        {
                            foreach($fields as $key=>$value)
                            {
                                if(is_array($value))
                                {
                                    for($i=0;$i<count($value);$i++)
                                    {
                                        $value[$i]=new \MongoRegex('/.*'.$value[$i].'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value
                                    );
                                }
                                else
                                {
                                    $plantunits->field('attributes.'.$key)->in(
                                        array(
                                            new \MongoRegex('/.*'.$value.'.*/i')
                                        )
                                    );
                                }
                            }
                        }
                        $ids_c=$plantunits
                            ->getQuery()
                            ->execute();
                        $ids_tab=array();
                        foreach($ids_c as $id)
                        {
                            $ids_tab[$id['_id']->{'$id'}]=$id['_id']->{'$id'};
                        }
                        unset($ids_c);
                        $locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                            ->field('plantunit.id')->in($ids_tab)
                            ->getQuery()
                            ->execute();
                        $nbResults=count($locations);
                    }
                    $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                    $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                    return $this->render('PlantnetDataBundle:Frontend\Module:module_result.html.twig',array(
                        'project'=>$project,
                        'collection'=>$collection,
                        'module_parent'=>$module,
                        'module'=>$module,
                        'display'=>$display,
                        'layers'=>$layers,
                        'locations'=>$locations,
                        'nbResults'=>$nbResults,
                        'url'=>$url,
                        'current'=>'collection',
                        'current_display'=>'locations'
                    ));
                    break;
            }
        }
        else
        {
            return $this->redirect($this->generateUrl('_module_search',array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
            )));
        }
        return $this->render('PlantnetDataBundle:Frontend\Module:module_result.html.twig',array(
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'nbResults'=>0,
            'current'=>'collection'
        ));
    }

    /**
     * @Route("/project/{project}/credits", name="_credits")
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
     * @Route("/project/{project}/mentions", name="_mentions")
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
     * @Route("/project/{project}/contacts", name="_contacts")
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