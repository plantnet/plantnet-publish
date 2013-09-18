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
 * Search controller.
 *
 * @Route("")
 */
class SearchController extends Controller
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

    private function createModuleSearchForm($fields,$module,$sub_fields)
    {
        $properties=$module->getProperties();
        $tab_prop=array();
        foreach($properties as $prop){
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
        foreach($fields as $field){
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
        if(count($sub_fields)){
            foreach($sub_fields as $sub_ids=>$sub_module){
                $sub_properties=$sub_module->getProperties();
                $tab_prop=array();
                foreach($sub_properties as $prop){
                    $tab_prop[$prop->getId()]=$prop;
                }
                unset($sub_properties);
                $field=$sub_ids;
                $field=substr($field,strpos($field,'#')+1);
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
                    'data'=>$sub_ids
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
        }
        $form=$form->getForm();
        return $form;
    }

    /**
     * @Route("/project/{project}/collection/{collection}/{module}/search", name="front_module_search")
     * @Template()
     */
    public function module_searchAction($project,$collection,$module)
    {
        $this->check_enable_project($project);
        $translations=$this->make_translations(
            $project,
            $this->container->get('request')->get('_route'),
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
        $sub_fields=array();
        $children=$module->getChildren();
        if(count($children)){
            foreach($children as $child){
                if($child->getType()=='other'){
                    $sub_field=$child->getProperties();
                    foreach($sub_field as $row){
                        if($row->getSearch()==true){
                            $sub_fields[$child->getId().'#'.$row->getId()]=$child;
                        }
                    }
                }
            }
        }
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers_search.xml',0,true);
        $form=$this->createModuleSearchForm($fields,$module,$sub_fields);
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:module_search.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'layers'=>$layers,
            'form'=>$form->createView(),
            'nb_fields'=>count($fields)+count($sub_fields),
            'translations'=>$translations,
            'current'=>'collection'
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/result",
     *      defaults={"mode"="grid"},
     *      name="front_module_result"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/result/{mode}",
     *      requirements={"mode"="\w+"},
     *      name="front_module_result_mode"
     *  )
     * @Method("get")
     * @Template()
     */
    public function module_resultAction($project,$collection,$module,$mode,Request $request)
    {
        $this->check_enable_project($project);
        $translations=$this->make_translations(
            $project,
            'front_module_search',
            array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module
            )
        );
        //
        $this->get_config($project);
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
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
        $sub_fields=array();
        $children=$module->getChildren();
        if(count($children)){
            foreach($children as $child){
                if($child->getType()=='other'){
                    $sub_field=$child->getProperties();
                    foreach($sub_field as $row){
                        if($row->getSearch()==true){
                            $sub_fields[$child->getId().'#'.$row->getId()]=$child;
                        }
                    }
                }
            }
        }
        $form=$this->createModuleSearchForm($fields,$module,$sub_fields);
        if($request->isMethod('GET')){
            $form->bind($request);
            $data=$form->getData();
            $dm=$this->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
            // Location Filters
            $ids_punit=array();
            if(isset($data['x_lng_1_bottom_left'])&&!empty($data['x_lng_1_bottom_left'])){
                $locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                    ->field('coordinates')->withinBox(
                        floatval($data['x_lng_1_bottom_left']),
                        floatval($data['y_lat_1_bottom_left']),
                        floatval($data['x_lng_2_top_right']),
                        floatval($data['y_lat_2_top_right']))
                    ->hydrate(false)
                    ->getQuery()
                    ->execute();
                foreach($locations as $location){
                    $ids_punit[]=$location['plantunit']['$id']->{'$id'};
                }
                unset($locations);
            }
            $ids_punit=array_unique($ids_punit);
            // Field (and sub-field) Filters
            $fields=array();
            $sub_fields=array();
            foreach($data as $key=>$val){
                if(substr_count($key,'name_field_')){
                    if(isset($data[str_replace('name_','',$key).'_string'])&&!empty($data[str_replace('name_','',$key).'_string'])){
                        if(substr_count($val,'#')===0){
                            $fields[$val]=explode('~|~',$data[str_replace('name_','',$key).'_string']);
                        }
                        else{
                            $sub_fields[$val]=explode('~|~',$data[str_replace('name_','',$key).'_string']);
                        }
                    }
                    elseif(isset($data[str_replace('name_','',$key)])&&!empty($data[str_replace('name_','',$key)])){
                        if(substr_count($val,'#')===0){
                            $fields[$val]=$data[str_replace('name_','',$key)];
                        }
                        else{
                            $sub_fields[$val]=$data[str_replace('name_','',$key)];
                        }
                    }
                }
            }
            // Sub-field Filters
            $ids_other=array();
            if(count($sub_fields)){
                foreach($sub_fields as $sub_ids=>$value){
                    $sub_module_id=substr($sub_ids,0,strpos($sub_ids,'#'));
                    $sub_attribute_id=substr($sub_ids,strpos($sub_ids,'#')+1);
                    $sub_module=$dm->getRepository('PlantnetDataBundle:Module')
                        ->findOneBy(array(
                            'id'=>$sub_module_id,
                            'parent.id'=>$module->getId()
                        ));
                    if(!$sub_module){
                        throw $this->createNotFoundException('Unable to find Module entity.');
                    }
                    $others=$dm->createQueryBuilder('PlantnetDataBundle:Other')
                        ->hydrate(false)
                        ->field('module')->references($sub_module);
                    if(is_array($value)){
                        for($i=0;$i<count($value);$i++){
                            $value[$i]=new \MongoRegex('/.*'.StringHelp::accentToRegex($value[$i]).'.*/i');
                        }
                        $others->field('property.'.$sub_attribute_id)->in(
                            $value
                        );
                    }
                    else{
                        $others->field('property.'.$sub_attribute_id)->in(
                            array(
                                new \MongoRegex('/.*'.StringHelp::accentToRegex($value).'.*/i')
                            )
                        );
                    }
                    $others=$others->getQuery()
                        ->execute();
                    $tmp_ids_other=array();
                    foreach($others as $other){
                        $tmp_ids_other[]=$other['plantunit']['$id']->{'$id'};
                    }
                    unset($others);
                    $ids_other=array_merge($ids_other,$tmp_ids_other);
                }
            }
            $ids_other=array_unique($ids_other);
            // $ids_punit x $ids_other
            if(!count($ids_punit)){
                $ids_punit=$ids_other;
            }
            elseif(count($ids_other)){
                $ids_punit=array_intersect($ids_punit,$ids_other);
            }
            // Filters to URL
            $url='';
            $data_url=$form->getData();
            foreach($data_url as $key=>$val){
                if($url!=''){
                    $url.='&';
                }
                if(substr_count($val,'#')===0){
                    $url.=$form->getName().'['.$key.']='.$val;
                }
                else{
                    $url.=$form->getName().'['.$key.']='.urlencode($val);
                }
            }
            // Search
            switch($mode){
                case 'grid':
                    $paginator=null;
                    $nbResults=0;
                    $nb_images=0;
                    $nb_locations=0;
                    $sortby=$this->get('request')->query->get('sort','null');
                    $sortorder=$this->get('request')->query->get('order','null');
                    if(!in_array($sortorder,array('null','asc','desc'))){
                        $sortorder='null';
                    }
                    if(count($ids_punit)||count($fields)){
                        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->field('module.id')->equals($module->getId());
                        if(count($ids_punit)){
                            $plantunits->field('_id')->in($ids_punit);
                        }
                        if(count($fields)){
                            foreach($fields as $key=>$value){
                                if(is_array($value)){
                                    for($i=0;$i<count($value);$i++){
                                        $value[$i]=new \MongoRegex('/.*'.StringHelp::accentToRegex($value[$i]).'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value
                                    );
                                }
                                else{
                                    $plantunits->field('attributes.'.$key)->in(
                                        array(
                                            new \MongoRegex('/.*'.StringHelp::accentToRegex($value).'.*/i')
                                        )
                                    );
                                }
                            }
                        }
                        $order=array();
                        $field=$module->getProperties();
                        foreach($field as $row){
                            if($row->getSortorder()){
                                $order[$row->getSortorder()]=$row->getId();
                            }
                        }
                        ksort($order);
                        if($sortby!='null'&&$sortorder!='null'){
                            if(in_array($sortby,$order)){
                                unset($order[array_search($sortby,$order)]);
                            }
                            $plantunits->sort('attributes.'.$sortby,$sortorder);
                        }
                        if(count($order)){
                            foreach($order as $num=>$prop){
                                $plantunits->sort('attributes.'.$prop,'asc');
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
                        //count to display
                        $clone_plantunits=clone $plantunits;
                        $ids_c=$clone_plantunits->hydrate(false)
                            ->select('_id')
                            ->getQuery()
                            ->execute();
                        $ids_tab=array();
                        foreach($ids_c as $id){
                            $ids_tab[$id['_id']->{'$id'}]=$id['_id']->{'$id'};
                        }
                        unset($ids_c);
                        $nb_images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('plantunit.id')->in($ids_tab)
                            ->getQuery()
                            ->execute()
                            ->count();
                        $nb_locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('plantunit.id')->in($ids_tab)
                            ->getQuery()
                            ->execute()
                            ->count();
                    }
                    $config=$this->get_config($project);
                    $tpl=$config->getTemplate();
                    return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:module_result.html.twig',array(
                        'config'=>$config,
                        'project'=>$project,
                        'collection'=>$collection,
                        'module'=>$module,
                        'paginator'=>$paginator,
                        'display'=>$display,
                        'sortby'=>$sortby,
                        'sortorder'=>$sortorder,
                        'nbResults'=>$nbResults,
                        'nb_images'=>$nb_images,
                        'nb_locations'=>$nb_locations,
                        'url'=>$url,
                        'translations'=>$translations,
                        'current'=>'collection',
                        'current_display'=>'grid'
                    ));
                    break;
                case 'images':
                    $paginator=null;
                    $nbResults=0;
                    $nb_images=0;
                    $nb_locations=0;
                    if(count($ids_punit)||count($fields)){
                        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('module.id')->equals($module->getId());
                        if(count($ids_punit)){
                            $plantunits->field('_id')->in($ids_punit);
                        }
                        if(count($fields)){
                            foreach($fields as $key=>$value){
                                if(is_array($value)){
                                    for($i=0;$i<count($value);$i++){
                                        $value[$i]=new \MongoRegex('/.*'.StringHelp::accentToRegex($value[$i]).'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value
                                    );
                                }
                                else{
                                    $plantunits->field('attributes.'.$key)->in(
                                        array(
                                            new \MongoRegex('/.*'.StringHelp::accentToRegex($value).'.*/i')
                                        )
                                    );
                                }
                            }
                        }
                        $ids_c=$plantunits
                            ->getQuery()
                            ->execute();
                        $ids_tab=array();
                        foreach($ids_c as $id){
                            $ids_tab[$id['_id']->{'$id'}]=$id['_id']->{'$id'};
                        }
                        unset($ids_c);
                        $images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                            ->field('plantunit.id')->in($ids_tab)
                            ->sort('title1','asc')
                            ->sort('title2','asc');
                        $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($images));
                        try{
                            $paginator->setMaxPerPage(15);
                            $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                        }
                        catch(\Pagerfanta\Exception\NotValidCurrentPageException $e){
                            throw $this->createNotFoundException('Page not found.');
                        }
                        $nbResults=$paginator->getNbResults();
                        //count to display
                        $nb_images=$nbResults;
                        $nb_locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('plantunit.id')->in($ids_tab)
                            ->getQuery()
                            ->execute()
                            ->count();
                    }
                    $config=$this->get_config($project);
                    $tpl=$config->getTemplate();
                    return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:module_result.html.twig',array(
                        'config'=>$config,
                        'project'=>$project,
                        'collection'=>$collection,
                        'module_parent'=>$module,
                        'module'=>$module,
                        'paginator'=>$paginator,
                        'display'=>$display,
                        'nbResults'=>$nbResults,
                        'nb_images'=>$nb_images,
                        'nb_locations'=>$nb_locations,
                        'url'=>$url,
                        'translations'=>$translations,
                        'current'=>'collection',
                        'current_display'=>'images'
                    ));
                    break;
                case 'locations':
                    $locations=null;
                    $nbResults=0;
                    if(count($ids_punit)||count($fields)){
                        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('module.id')->equals($module->getId());
                        if(count($ids_punit)){
                            $plantunits->field('_id')->in($ids_punit);
                        }
                        if(count($fields)){
                            foreach($fields as $key=>$value){
                                if(is_array($value)){
                                    for($i=0;$i<count($value);$i++){
                                        $value[$i]=new \MongoRegex('/.*'.StringHelp::accentToRegex($value[$i]).'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value
                                    );
                                }
                                else{
                                    $plantunits->field('attributes.'.$key)->in(
                                        array(
                                            new \MongoRegex('/.*'.StringHelp::accentToRegex($value).'.*/i')
                                        )
                                    );
                                }
                            }
                        }
                        $ids_c=$plantunits
                            ->getQuery()
                            ->execute();
                        $ids_tab=array();
                        foreach($ids_c as $id){
                            $ids_tab[$id['_id']->{'$id'}]=$id['_id']->{'$id'};
                        }
                        unset($ids_c);
                        $locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                            ->field('plantunit.id')->in($ids_tab)
                            ->getQuery()
                            ->execute();
                        $nbResults=count($locations);
                        //count to display
                        $nb_locations=$nbResults;
                        $nb_images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('plantunit.id')->in($ids_tab)
                            ->getQuery()
                            ->execute()
                            ->count();
                    }
                    $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                    $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                    $config=$this->get_config($project);
                    $tpl=$config->getTemplate();
                    return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:module_result.html.twig',array(
                        'config'=>$config,
                        'project'=>$project,
                        'collection'=>$collection,
                        'module_parent'=>$module,
                        'module'=>$module,
                        'display'=>$display,
                        'layers'=>$layers,
                        'locations'=>$locations,
                        'nbResults'=>$nbResults,
                        'nb_images'=>$nb_images,
                        'nb_locations'=>$nb_locations,
                        'url'=>$url,
                        'translations'=>$translations,
                        'current'=>'collection',
                        'current_display'=>'locations'
                    ));
                    break;
            }
        }
        else{
            return $this->redirect($this->generateUrl('front_module_search',array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
            )));
        }
        $config=$this->get_config($project);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:module_result.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'translations'=>$translations,
            'nbResults'=>0,
            'current'=>'collection'
        ));
    }

    /**
    * @Route(
     *      "/project/{project}/collection/{collection}/{module}/search/{attribute}/{query}",
     *      defaults={"attribute"="null", "query"="null"},
     *      name="front_module_search_query_path"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/search/{attribute}/{query}",
     *      name="front_module_search_query"
     *  )
     * @Template()
     */
    public function module_search_queryAction($project,$collection,$module,$attribute,$query)
    {
        $this->check_enable_project($project);
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
        $results=array();
        if(substr_count($attribute,'#')==1){
            $sub_module_id=substr($attribute,0,strpos($attribute,'#'));
            $sub_attribute_id=substr($attribute,strpos($attribute,'#')+1);
            $sub_module=$dm->getRepository('PlantnetDataBundle:Module')
                ->findOneBy(array(
                    'id'=>$sub_module_id,
                    'parent.id'=>$module->getId()
                ));
            if(!$sub_module){
                throw $this->createNotFoundException('Unable to find Module entity.');
            }
            $others=$dm->createQueryBuilder('PlantnetDataBundle:Other')
                ->hydrate(false)
                ->distinct('property.'.$sub_attribute_id)
                ->select('property.'.$sub_attribute_id)
                ->field('module')->references($sub_module)
                ->field('property.'.$sub_attribute_id)->in(array(
                    new \MongoRegex('/.*'.StringHelp::accentToRegex($query).'.*/i')
                ))
                ->sort('property.'.$sub_attribute_id,'asc')
                ->limit(10)
                ->getQuery()
                ->execute();
            foreach($others as $other){
               $results[]=$other;
            }
        }
        else{
            $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->hydrate(false)
                ->distinct('attributes.'.$attribute)
                ->select('attributes.'.$attribute)
                ->field('module')->references($module)
                ->field('attributes.'.$attribute)->in(array(
                    new \MongoRegex('/.*'.StringHelp::accentToRegex($query).'.*/i')
                ))
                ->sort('attributes.'.$attribute,'asc')
                ->limit(10)
                ->getQuery()
                ->execute();
            foreach($plantunits as $punit){
                $results[]=$punit;
            }
        }
        $response=new Response(json_encode($results));
        $response->headers->set('Content-Type','application/json');
        return $response;
        exit;
    }
}