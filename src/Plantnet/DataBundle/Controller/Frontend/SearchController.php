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

use Plantnet\DataBundle\Utils\StringSearch;


/**
 * Search  controller.
 *
 * @Route("")
 */
class SearchController extends Controller
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

    private function createModuleSearchForm($fields,$module)
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
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/result",
     *      defaults={"mode"="grid"},
     *      name="_module_result"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/result/{mode}",
     *      requirements={"mode"="\w+"},
     *      name="_module_result_mode"
     *  )
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
        if($request->isMethod('GET')){
            $form->bind($request);
            $data=$form->getData();
            $dm=$this->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
            $ids_punit=array();
            // Location Filters
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
            // Field Filters
            $fields=array();
            foreach($data as $key=>$val){
                if(substr_count($key,'name_field_')){
                    if(isset($data[str_replace('name_','',$key).'_string'])&&!empty($data[str_replace('name_','',$key).'_string'])){
                        $fields[$val]=explode('~|~',$data[str_replace('name_','',$key).'_string']);
                    }
                    elseif(isset($data[str_replace('name_','',$key)])&&!empty($data[str_replace('name_','',$key)])){
                        $fields[$val]=$data[str_replace('name_','',$key)];
                    }
                }
            }
            // Filters to URL
            $url='';
            $data_url=$form->getData();
            foreach($data_url as $key=>$val){
                if($url!=''){
                    $url.='&';
                }
                $url.=$form->getName().'['.$key.']='.$val;
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
                                        $value[$i]=new \MongoRegex('/.*'.StringSearch::accentToRegex($value[$i]).'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value
                                    );
                                }
                                else{
                                    $plantunits->field('attributes.'.$key)->in(
                                        array(
                                            new \MongoRegex('/.*'.StringSearch::accentToRegex($value).'.*/i')
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
                    return $this->render('PlantnetDataBundle:Frontend\Module:module_result.html.twig',array(
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
                                        $value[$i]=new \MongoRegex('/.*'.StringSearch::accentToRegex($value[$i]).'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value
                                    );
                                }
                                else{
                                    $plantunits->field('attributes.'.$key)->in(
                                        array(
                                            new \MongoRegex('/.*'.StringSearch::accentToRegex($value).'.*/i')
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
                    return $this->render('PlantnetDataBundle:Frontend\Module:module_result.html.twig',array(
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
                                        $value[$i]=new \MongoRegex('/.*'.StringSearch::accentToRegex($value[$i]).'.*/i');
                                    }
                                    $plantunits->field('attributes.'.$key)->in(
                                        $value
                                    );
                                }
                                else{
                                    $plantunits->field('attributes.'.$key)->in(
                                        array(
                                            new \MongoRegex('/.*'.StringSearch::accentToRegex($value).'.*/i')
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
                    return $this->render('PlantnetDataBundle:Frontend\Module:module_result.html.twig',array(
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
                        'current'=>'collection',
                        'current_display'=>'locations'
                    ));
                    break;
            }
        }
        else{
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
}