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
 * Taxo controller.
 *
 * @Route("")
 */
class TaxoController extends Controller
{
    private function get_prefix()
    {
        return $this->container->getParameter('mdb_base').'_';
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo",
     *      defaults={"taxon"="null"},
     *      name="front_module_taxo"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo/{taxon}",
     *      name="front_module_taxo_details"
     *  )
     * @Method("get")
     * @Template()
     */
    public function module_taxoAction($project,$collection,$module,$taxon,Request $request)
    {
        echo "front_module_taxo_details";
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $form_identifier=$request->query->get('form_identifier');
        if($this->container->get('request')->get('_route')=='front_module_taxo'&&!empty($form_identifier)){
            return $this->redirect($this->generateUrl('front_module_taxo_details',array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
                'taxon'=>$form_identifier
            )),301);
        }
        //
        $translations=ControllerHelp::make_translations(
            $project,
            'front_module_taxo',
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
        if($taxon=='null'){
            $search_taxon=$request->query->get('taxon');
            if(!empty($search_taxon)){
                $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                    ->field('module')->references($module)
                    ->field('identifier')->in(array(
                        new \MongoRegex('/.*'.StringHelp::accentToRegex($search_taxon).'.*/i')
                    ))
                    ->sort('name','asc')
                    ->getQuery()
                    ->execute();
            }
            else{
                if($module->getDisplaysyns()==true){
                    $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                        ->field('module')->references($module)
                        ->field('parent')->equals(null)
                        ->sort('name','asc')
                        ->getQuery()
                        ->execute();
                }
                else{
                    $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                        ->field('module')->references($module)
                        ->field('parent')->equals(null)
                        ->field('issynonym')->equals(false)
                        ->sort('name','asc')
                        ->getQuery()
                        ->execute();
                }
            }
        }
        else{
            $taxon=$dm->getRepository('PlantnetDataBundle:Taxon')
                ->findOneBy(array(
                    'module.id'=>$module->getId(),
                    'identifier'=>$taxon
                ));
            if(!$taxon){
                throw $this->createNotFoundException('Unable to find Taxon entity.');
            }
            /*
            $tab_id=array($taxon->getId());
            $syns=$taxon->getSynonyms();
            if(count($syns)){
                foreach($syns as $syn){
                    $tab_id[]=$syn->getId();
                }
            }
            $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                ->field('module')->references($module)
                ->field('parent.id')->in($tab_id)
                ->field('issynonym')->equals(false)
                ->sort('name','asc')
                ->getQuery()
                ->execute();
            */
            $taxons=array($taxon);
        }
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:taxo.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'taxon'=>$taxon,
            'taxons'=>$taxons,
            'translations'=>$translations,
            'current'=>'collection',
            'selected'=>'taxo'.$collection->getId().$module->getId()
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_children",
     *      defaults={"parent"=0},
     *      name="front_module_taxo_children"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_children/{parent}",
     *      requirements={"parent"="\w+"},
     *      name="front_module_taxo_children_parent"
     *  )
     * @Template()
     */
    public function module_taxo_childrenAction($project,$collection,$module,$parent)
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
        if(!$module||$module->getType()!='text'||$module->getWsonly()==true){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $parent=$dm->getRepository('PlantnetDataBundle:Taxon')
            ->findOneBy(array(
                'module.id'=>$module->getId(),
                'id'=>$parent
            ));
        if(!$parent){
            throw $this->createNotFoundException('Unable to find Taxon entity.');
        }
        $tab_id=array($parent->getId());
        $syns=$parent->getSynonyms();
        if(count($syns)){
            foreach($syns as $syn){
                $tab_id[]=$syn->getId();
            }
        }
        if($module->getDisplaysyns()==true){
            $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                ->field('module')->references($module)
                ->field('parent.id')->in($tab_id)
                ->sort('name','asc')
                ->getQuery()
                ->execute();
        }
        else{
            $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                ->field('module')->references($module)
                ->field('parent.id')->in($tab_id)
                ->field('issynonym')->equals(false)
                ->sort('name','asc')
                ->getQuery()
                ->execute();
        }
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:taxo_children.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'taxons'=>$taxons,
            'current'=>'collection',
            'selected'=>'taxo'.$collection->getId().$module->getId()
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_query",
     *      defaults={"query"="null"},
     *      name="front_module_taxo_query_path"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_query/{query}",
     *      name="front_module_taxo_query"
     *  )
     * @Template()
     */
    public function module_taxo_queryAction($project,$collection,$module,$query)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        if($query=='null'){
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
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='text'||$module->getWsonly()==true){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->hydrate(false)
            ->select('name','identifier','label','issynonym')
            ->field('module')->references($module)
            ->field('name')->in(array(
                new \MongoRegex('/.*'.StringHelp::accentToRegex($query).'.*/i')
            ))
            ->sort('name','asc')
            ->limit(10)
            ->getQuery()
            ->execute();
        $results=array();
        foreach($taxons as $tax){
            $results[]=array(
                'identifier'=>$tax['identifier'],
                'name'=>$tax['name'],
                'label'=>$tax['label'],
                'issynonym'=>$tax['issynonym']
            );
        }
        $response=new Response(json_encode($results));
        $response->headers->set('Content-Type','application/json');
        return $response;
        exit;
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view/{taxon}",
     *      defaults={"page"=1, "sortby"="null", "sortorder"="null"},
     *      name="front_module_taxo_view"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view/{taxon}/page{page}",
     *      defaults={"sortby"="null", "sortorder"="null"},
     *      requirements={"page"="\d+"},
     *      name="front_module_taxo_view_paginated"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view/{taxon}/page{page}/sort-{sortby}/order-{sortorder}",
     *      requirements={"page"="\d+", "sortby"="\w+", "sortorder"="null|asc|desc"},
     *      name="front_module_taxo_view_paginated_sorted"
     *  )
     * @Method("get")
     * @Template()
     */
    public function module_taxo_viewAction($project,$collection,$module,$taxon,$page,$sortby,$sortorder,Request $request)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $form_page=$request->query->get('form_page');
        if(!empty($form_page)){
            $page=$form_page;
        }
        if($this->container->get('request')->get('_route')=='front_module_taxo_view_paginated'&&$page==1){
            return $this->redirect($this->generateUrl('front_module_taxo_view',array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
                'taxon'=>$taxon
                )
            ),301);
        }
        //
        $translations=ControllerHelp::make_translations(
            $project,
            'front_module_taxo',
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
        $taxon=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->field('module')->references($module)
            ->field('identifier')->equals($taxon)
            ->getQuery()
            ->getSingleResult();
        if(!$taxon){
            throw $this->createNotFoundException('Unable to find Taxon entity.');
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
        $tab_ref=array(
            $taxon->getId()=>$taxon
        );
        $syns=$taxon->getSynonyms();
        if(count($syns)){
            foreach($syns as $syn){
                $tab_ref[$syn->getId()]=$syn;
            }
        }
        /*
        $children=$taxon->getChildren();
        while(count($children)){
            $children_new=array();
            foreach($children as $child){
                $tab_ref[$child->getId()]=$child;
                $syns=$child->getSynonyms();
                if(count($syns)){
                    foreach($syns as $syn){
                        $tab_ref[$syn->getId()]=$syn;
                    }
                }
                $tmp_children=$child->getChildren();
                if(count($tmp_children)){
                    foreach($tmp_children as $new_child){
                        $children_new[$new_child->getId()]=$new_child;
                    }
                }
            }
            $children=$children_new;
        }
        */
        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit');
        $plantunits->field('module')->references($module);
        if(count($tab_ref)>1){
            foreach($tab_ref as $ref){
                $plantunits->addOr($plantunits->expr()->field('taxonsrefs')->references($ref));
            }
        }
        else{
            $plantunits->field('taxonsrefs')->references($tab_ref[key($tab_ref)]);
        }
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
            $paginator->setCurrentPage($page);
        }
        catch(\Pagerfanta\Exception\NotValidCurrentPageException $e){
            throw $this->createNotFoundException('Page not found.');
        }
        //count to display
        $nb_images=($taxon->getHasimages())?1:0;
        $nb_locations=($taxon->getHaslocations())?1:0;
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:taxo_view.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'taxon'=>$taxon,
            'paginator'=>$paginator,
            'nbResults'=>$paginator->getNbResults(),
            'nb_images'=>$nb_images,
            'nb_locations'=>$nb_locations,
            'display'=>$display,
            'page'=>$page,
            'sortby'=>$sortby,
            'sortorder'=>$sortorder,
            'translations'=>$translations,
            'current'=>'collection',
            'current_display'=>'grid',
            'selected'=>'taxo'.$collection->getId().$module->getId()
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view_gallery/{taxon}",
     *      defaults={"page"=1},
     *      name="front_module_taxo_view_gallery"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view_gallery/{taxon}/page{page}",
     *      requirements={"page"="\d+"},
     *      name="front_module_taxo_view_gallery_paginated"
     *  )
     * @Method("get")
     * @Template()
     */
    public function module_taxo_view_galleryAction($project,$collection,$module,$taxon,$page,Request $request)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $form_page=$request->query->get('form_page');
        if(!empty($form_page)){
            $page=$form_page;
        }
        if($this->container->get('request')->get('_route')=='front_module_taxo_view_gallery_paginated'&&$page==1){
            return $this->redirect($this->generateUrl('front_module_taxo_view_gallery',array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
                'taxon'=>$taxon
                )
            ),301);
        }
        //
        $translations=ControllerHelp::make_translations(
            $project,
            'front_module_taxo',
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
        $taxon=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->field('module')->references($module)
            ->field('identifier')->equals($taxon)
            ->getQuery()
            ->getSingleResult();
        if(!$taxon){
            throw $this->createNotFoundException('Unable to find Taxon entity.');
        }
        $display=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getMain()==true){
                $display[]=$row->getId();
            }
        }
        $tab_ref=array(
            $taxon->getId()=>$taxon
        );
        $syns=$taxon->getSynonyms();
        if(count($syns)){
            foreach($syns as $syn){
                $tab_ref[$syn->getId()]=$syn;
            }
        }
        /*
        $children=$taxon->getChildren();
        while(count($children)){
            $children_new=array();
            foreach($children as $child){
                $tab_ref[$child->getId()]=$child;
                $syns=$child->getSynonyms();
                if(count($syns)){
                    foreach($syns as $syn){
                        $tab_ref[$syn->getId()]=$syn;
                    }
                }
                $tmp_children=$child->getChildren();
                if(count($tmp_children)){
                    foreach($tmp_children as $new_child){
                        $children_new[$new_child->getId()]=$new_child;
                    }
                }
            }
            $children=$children_new;
        }
        */
        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit');
        $plantunits->field('module')->references($module);
        $plantunits->hydrate(false);
        $plantunits->select('_id');
        if(count($tab_ref)>1){
            foreach($tab_ref as $ref){
                $plantunits->addOr($plantunits->expr()->field('taxonsrefs')->references($ref));
            }
        }
        else{
            $plantunits->field('taxonsrefs')->references($tab_ref[key($tab_ref)]);
        }
        $plantunits=$plantunits->getQuery()->execute();
        $pu_ids=array();
        foreach($plantunits as $id){
            $pu_ids[]=$id['_id'];
        }
        $images=$dm->createQueryBuilder('PlantnetDataBundle:Image');
        $images->field('plantunit.$id')->in($pu_ids);
        /*
        $images=$dm->createQueryBuilder('PlantnetDataBundle:Image');
        if(count($tab_ref)>1){
            foreach($tab_ref as $ref){
                $images->addOr($images->expr()->field('taxonsrefs')->references($ref));
            }
        }
        else{
            $images->field('taxonsrefs')->references($tab_ref[key($tab_ref)]);
        }
        */
        $images->sort('title1','asc');
        $images->sort('title2','asc');
        $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($images));
        try{
            $paginator->setMaxPerPage(15);
            $paginator->setCurrentPage($page);
        }
        catch(\Pagerfanta\Exception\NotValidCurrentPageException $e){
            throw $this->createNotFoundException('Page not found.');
        }
        //count to display
        $nb_images=1;
        $nb_locations=($taxon->getHaslocations())?1:0;
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:taxo_view.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module_parent'=>$module,
            'module'=>$module,
            'taxon'=>$taxon,
            'paginator'=>$paginator,
            'nbResults'=>$paginator->getNbResults(),
            'nb_images'=>$nb_images,
            'nb_locations'=>$nb_locations,
            'display'=>$display,
            'page'=>$page,
            'translations'=>$translations,
            'current'=>'collection',
            'current_display'=>'images',
            'selected'=>'taxo'.$collection->getId().$module->getId()
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view_map/{taxon}",
     *      name="front_module_taxo_view_map"
     *  )
     * @Template()
     */
    public function module_taxo_view_mapAction($project,$collection,$module,$taxon)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        $translations=ControllerHelp::make_translations(
            $project,
            'front_module_taxo',
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
        $taxon=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->field('module')->references($module)
            ->field('identifier')->equals($taxon)
            ->getQuery()
            ->getSingleResult();
        if(!$taxon){
            throw $this->createNotFoundException('Unable to find Taxon entity.');
        }
        $display=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getMain()==true){
                $display[]=$row->getId();
            }
        }
        $tab_ref=array(
            $taxon->getId()=>$taxon
        );
        $syns=$taxon->getSynonyms();
        if(count($syns)){
            foreach($syns as $syn){
                $tab_ref[$syn->getId()]=$syn;
            }
        }
        /*
        $children=$taxon->getChildren();
        while(count($children)){
            $children_new=array();
            foreach($children as $child){
                $tab_ref[$child->getId()]=$child;
                $syns=$child->getSynonyms();
                if(count($syns)){
                    foreach($syns as $syn){
                        $tab_ref[$syn->getId()]=$syn;
                    }
                }
                $tmp_children=$child->getChildren();
                if(count($tmp_children)){
                    foreach($tmp_children as $new_child){
                        $children_new[$new_child->getId()]=$new_child;
                    }
                }
            }
            $children=$children_new;
        }
        */
        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit');
        $plantunits->field('module')->references($module);
        $plantunits->hydrate(false);
        $plantunits->select('_id');
        if(count($tab_ref)>1){
            foreach($tab_ref as $ref){
                $plantunits->addOr($plantunits->expr()->field('taxonsrefs')->references($ref));
            }
        }
        else{
            $plantunits->field('taxonsrefs')->references($tab_ref[key($tab_ref)]);
        }
        $plantunits=$plantunits->getQuery()->execute();
        $pu_ids=array();
        foreach($plantunits as $id){
            $pu_ids[]=$id['_id'];
        }
        $locations=$dm->createQueryBuilder('PlantnetDataBundle:Location');
        $locations->field('plantunit.$id')->in($pu_ids);
        /*
        $locations=$dm->createQueryBuilder('PlantnetDataBundle:Location');
        if(count($tab_ref)>1){
            foreach($tab_ref as $ref){
                $locations->addOr($locations->expr()->field('taxonsrefs')->references($ref));
            }
        }
        else{
            $locations->field('taxonsrefs')->references($tab_ref[key($tab_ref)]);
        }
        */
        $locations=$locations->getQuery()
            ->execute();
        //count to display
        $nb_images=($taxon->getHasimages())?1:0;
        $nb_locations=1;
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
        $config=ControllerHelp::get_config($project,$dm,$this);
        $tpl=$config->getTemplate();
        return $this->render('PlantnetDataBundle:'.(($tpl)?$tpl:'Frontend').'\Module:taxo_view.html.twig',array(
            'config'=>$config,
            'project'=>$project,
            'collection'=>$collection,
            'module_parent'=>$module,
            'module'=>$module,
            'taxon'=>$taxon,
            'layers'=>$layers,
            'locations'=>$locations,
            'nbResults'=>count($locations),
            'nb_images'=>$nb_images,
            'nb_locations'=>$nb_locations,
            'display'=>$display,
            'translations'=>$translations,
            'current'=>'collection',
            'current_display'=>'locations',
            'selected'=>'taxo'.$collection->getId().$module->getId()
        ));
    }
}