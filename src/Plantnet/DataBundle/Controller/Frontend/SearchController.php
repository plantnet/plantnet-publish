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
 * Search controller.
 *
 * @Route("")
 */
class SearchController extends Controller
{
    private function get_prefix()
    {
        return $this->container->getParameter('mdb_base').'_';
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            $this->container->get('request')->get('_route'),
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
        if(!$module||$module->getWsonly()==true){
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
        $config=ControllerHelp::get_config($project,$dm,$this);
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
            'current'=>'collection',
            'selected'=>'searchform'.$collection->getId().$module->getId()
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            'front_module_search',
            array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module
            ),
            $this,
            $this->container->getParameter('mdb_base')
        );
        //
        // $this->get_config($project);
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        ControllerHelp::get_config($project,$dm,$this);
        $configuration=$dm->getConnection()->getConfiguration();
        // $configuration->setLoggerCallable(null);
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
                $locations=null;
                unset($locations);
            }
            $ids_punit=array_unique($ids_punit);
            // Field (and sub-field) Filters
            $fields=array();
            $sub_fields=array();
            foreach($data as $key=>$val){
                if(substr_count($key,'name_field_')&&$val){
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
                    $others=null;
                    unset($others);
                    $ids_other=array_merge($ids_other,$tmp_ids_other);
                    $tmp_ids_other=null;
                    unset($tmp_ids_other);
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
                                    $value_query_tab=array();
                                    for($i=0;$i<count($value);$i++){
                                        //check for int or float value
                                        if(is_numeric($value[$i])){
                                            $tmp_value=intval($value[$i]);
                                            if($value[$i]==$tmp_value){
                                                $value_query_tab[]=$tmp_value;
                                            }
                                            else{
                                                $tmp_value=floatval($value[$i]);
                                                if($value[$i]==$tmp_value){
                                                    $value_query_tab[]=$tmp_value;
                                                }
                                            }
                                        }
                                        //
                                        $value_query_tab[]=new \MongoRegex('/.*'.StringHelp::accentToRegex($value[$i]).'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value_query_tab
                                    );
                                }
                                else{
                                    $value_query_tab=array(
                                        new \MongoRegex('/.*'.StringHelp::accentToRegex($value).'.*/i')
                                    );
                                    //check for int or float value
                                    if(is_numeric($value)){
                                        $tmp_value=intval($value);
                                        if($value==$tmp_value){
                                            $value_query_tab[]=$tmp_value;
                                        }
                                        else{
                                            $tmp_value=floatval($value);
                                            if($value==$tmp_value){
                                                $value_query_tab[]=$tmp_value;
                                            }
                                        }
                                    }
                                    //
                                    $plantunits->field('attributes.'.$key)->in($value_query_tab);
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
                            // $ids_tab[$id['_id']->{'$id'}]=$id['_id']->{'$id'};
                            $ids_tab[$id['_id']->{'$id'}]=$id['_id'];
                        }
                        $clone_plantunits=null;
                        unset($clone_plantunits);
                        $ids_c=null;
                        unset($ids_c);
                        /*
                        $nb_images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('plantunit.id')->in(array_values($ids_tab))
                            ->getQuery()
                            ->execute()
                            ->count();
                        $nb_locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('plantunit.id')->in(array_values($ids_tab))
                            ->getQuery()
                            ->execute()
                            ->count();
                        */
                        $connection=new \MongoClient();
                        $db=$this->get_prefix().$project;
                        $db=$connection->$db;
                        $nb_images=$db->Image->find(array('plantunit.$id'=>array('$in'=>array_values($ids_tab))))->count();
                        $nb_locations=$db->Location->find(array('plantunit.$id'=>array('$in'=>array_values($ids_tab))))->count();
                        $ids_tab=null;
                        unset($ids_tab);
                    }
                    $config=ControllerHelp::get_config($project,$dm,$this);
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
                        'current_display'=>'grid',
                        'selected'=>'searchform'.$collection->getId().$module->getId()
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
                            $plantunits->field('_id')->in(array_values($ids_punit));
                        }
                        if(count($fields)){
                            foreach($fields as $key=>$value){
                                if(is_array($value)){
                                    $value_query_tab=array();
                                    for($i=0;$i<count($value);$i++){
                                        //check for int or float value
                                        if(is_numeric($value[$i])){
                                            $tmp_value=intval($value[$i]);
                                            if($value[$i]==$tmp_value){
                                                $value_query_tab[]=$tmp_value;
                                            }
                                            else{
                                                $tmp_value=floatval($value[$i]);
                                                if($value[$i]==$tmp_value){
                                                    $value_query_tab[]=$tmp_value;
                                                }
                                            }
                                        }
                                        //
                                        $value_query_tab[]=new \MongoRegex('/.*'.StringHelp::accentToRegex($value[$i]).'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value_query_tab
                                    );
                                }
                                else{
                                    $value_query_tab=array(
                                        new \MongoRegex('/.*'.StringHelp::accentToRegex($value).'.*/i')
                                    );
                                    //check for int or float value
                                    if(is_numeric($value)){
                                        $tmp_value=intval($value);
                                        if($value==$tmp_value){
                                            $value_query_tab[]=$tmp_value;
                                        }
                                        else{
                                            $tmp_value=floatval($value);
                                            if($value==$tmp_value){
                                                $value_query_tab[]=$tmp_value;
                                            }
                                        }
                                    }
                                    //
                                    $plantunits->field('attributes.'.$key)->in($value_query_tab);
                                }
                            }
                        }
                        $ids_c=$plantunits
                            ->getQuery()
                            ->execute();
                        $ids_tab=array();
                        foreach($ids_c as $id){
                            // $ids_tab[$id['_id']->{'$id'}]=$id['_id']->{'$id'};
                            $ids_tab[$id['_id']->{'$id'}]=$id['_id'];
                        }
                        $ids_c=null;
                        unset($ids_c);
                        $images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                            ->field('plantunit.id')->in(array_values($ids_tab))
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
                        /*
                        $nb_locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('plantunit.id')->in(array_values($ids_tab))
                            ->getQuery()
                            ->execute()
                            ->count();
                        */
                        $connection=new \MongoClient();
                        $db=$this->get_prefix().$project;
                        $db=$connection->$db;
                        $nb_locations=$db->Location->find(array('plantunit.$id'=>array('$in'=>array_values($ids_tab))))->count();
                        $ids_tab=null;
                        unset($ids_tab);
                    }
                    $config=ControllerHelp::get_config($project,$dm,$this);
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
                        'current_display'=>'images',
                        'selected'=>'searchform'.$collection->getId().$module->getId()
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
                            $plantunits->field('_id')->in(array_values($ids_punit));
                        }
                        if(count($fields)){
                            foreach($fields as $key=>$value){
                                if(is_array($value)){
                                    $value_query_tab=array();
                                    for($i=0;$i<count($value);$i++){
                                        //check for int or float value
                                        if(is_numeric($value[$i])){
                                            $tmp_value=intval($value[$i]);
                                            if($value[$i]==$tmp_value){
                                                $value_query_tab[]=$tmp_value;
                                            }
                                            else{
                                                $tmp_value=floatval($value[$i]);
                                                if($value[$i]==$tmp_value){
                                                    $value_query_tab[]=$tmp_value;
                                                }
                                            }
                                        }
                                        //
                                        $value_query_tab[]=new \MongoRegex('/.*'.StringHelp::accentToRegex($value[$i]).'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value_query_tab
                                    );
                                }
                                else{
                                    $value_query_tab=array(
                                        new \MongoRegex('/.*'.StringHelp::accentToRegex($value).'.*/i')
                                    );
                                    //check for int or float value
                                    if(is_numeric($value)){
                                        $tmp_value=intval($value);
                                        if($value==$tmp_value){
                                            $value_query_tab[]=$tmp_value;
                                        }
                                        else{
                                            $tmp_value=floatval($value);
                                            if($value==$tmp_value){
                                                $value_query_tab[]=$tmp_value;
                                            }
                                        }
                                    }
                                    //
                                    $plantunits->field('attributes.'.$key)->in($value_query_tab);
                                }
                            }
                        }
                        $ids_c=$plantunits
                            ->getQuery()
                            ->execute();
                        $ids_tab=array();
                        foreach($ids_c as $id){
                            // $ids_tab[$id['_id']->{'$id'}]=$id['_id']->{'$id'};
                            $ids_tab[$id['_id']->{'$id'}]=$id['_id'];
                        }
                        $ids_c=null;
                        unset($ids_c);
                        $locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                            ->field('plantunit.id')->in(array_values($ids_tab))
                            ->getQuery()
                            ->execute();
                        $nbResults=count($locations);
                        //count to display
                        $nb_locations=$nbResults;
                        /*
                        $nb_images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('plantunit.id')->in(array_values($ids_tab))
                            ->getQuery()
                            ->execute()
                            ->count();
                        */
                        $connection=new \MongoClient();
                        $db=$this->get_prefix().$project;
                        $db=$connection->$db;
                        $nb_images=$db->Image->find(array('plantunit.$id'=>array('$in'=>array_values($ids_tab))))->count();
                        $ids_tab=null;
                        unset($ids_tab);
                    }
                    $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                    $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                    $config=ControllerHelp::get_config($project,$dm,$this);
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
                        'current_display'=>'locations',
                        'selected'=>'searchform'.$collection->getId().$module->getId()
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
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:module_result.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'translations'=>$translations,
            'nbResults'=>0,
            'current'=>'collection',
            'selected'=>'searchform'.$collection->getId().$module->getId()
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
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        $results=array();
        $query_tab=array(
            new \MongoRegex('/.*'.StringHelp::accentToRegex($query).'.*/i')
        );
        //check for int or float value
        if(is_numeric($query)){
            $tmp_query=intval($query);
            if($query==$tmp_query){
                $query_tab[]=$tmp_query;
            }
            else{
                $tmp_query=floatval($query);
                if($query==$tmp_query){
                    $query_tab[]=$tmp_query;
                }
            }
        }
        //
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
                ->field('property.'.$sub_attribute_id)->in($query_tab)
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
                ->field('attributes.'.$attribute)->in($query_tab)
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