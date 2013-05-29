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
 * Taxo  controller.
 *
 * @Route("")
 */
class TaxoController extends Controller
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
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo",
     *      defaults={"level"=0, "taxon"="null"},
     *      name="_module_taxo"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo/{level}-{taxon}",
     *      requirements={"level"="\d+"},
     *      name="_module_taxo_details"
     *  )
     * @Method("get")
     * @Template()
     */
    public function module_taxoAction($project,$collection,$module,$level,$taxon,Request $request)
    {
        $form_level=$request->query->get('form_level');
        $form_name=$request->query->get('form_name');
        if($this->container->get('request')->get('_route')=='_module_taxo'&&!empty($form_level)&&!empty($form_name)){
            return $this->redirect($this->generateUrl('_module_taxo_view',array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
                'level'=>$form_level,
                'taxon'=>$form_name
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
        if($taxon=='null'){
            $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                ->field('module')->references($module)
                ->field('parent')->equals(null)
                ->sort('name','asc')
                ->getQuery()
                ->execute();
        }
        else{
            $taxon=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                ->field('module')->references($module)
                ->field('name')->equals($taxon)
                ->field('level')->equals(intval($level))
                ->getQuery()
                ->getSingleResult();
            if(!$taxon){
                throw $this->createNotFoundException('Unable to find Taxon entity.');
            }
            if(count($taxon->getChildren())==0){
                return $this->redirect($this->generateUrl('_module_taxo_view',array(
                    'project'=>$project,
                    'collection'=>$collection,
                    'module'=>$module,
                    'level'=>$level,
                    'taxon'=>$taxon
                    )
                ),301);
            }
            $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                ->field('module')->references($module)
                ->field('parent')->references($taxon)
                ->sort('name','asc')
                ->getQuery()
                ->execute();
        }
        return $this->render('PlantnetDataBundle:Frontend\Module:taxo.html.twig',array(
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'taxon'=>$taxon,
            'taxons'=>$taxons,
            'current'=>'collection'
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_children",
     *      defaults={"parent"=0},
     *      name="_module_taxo_children"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_children/{parent}",
     *      requirements={"parent"="\w+"},
     *      name="_module_taxo_children_parent"
     *  )
     * @Template()
     */
    public function module_taxo_childrenAction($project,$collection,$module,$parent)
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
        if(!$module||$module->getType()!='text'){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->field('module')->references($module)
            ->field('parent.id')->equals($parent)
            ->sort('name','asc')
            ->getQuery()
            ->execute();
        return $this->render('PlantnetDataBundle:Frontend\Module:taxo_children.html.twig',array(
            'project'=>$project,
            'collection'=>$collection,
            'module'=>$module,
            'taxons'=>$taxons,
            'current'=>'collection'
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_query",
     *      defaults={"query"="null"},
     *      name="_module_taxo_query_path"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_query/{query}",
     *      name="_module_taxo_query"
     *  )
     * @Template()
     */
    public function module_taxo_queryAction($project,$collection,$module,$query)
    {
        if($query=='null'){
            $response=new Response(json_encode(array()));
            $response->headers->set('Content-Type','application/json');
            return $response;
            exit;
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
        $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->hydrate(false)
            ->select('name','level','label')
            ->field('module')->references($module)
            ->field('name')->in(array(
                new \MongoRegex('/.*'.StringSearch::accentToRegex($query).'.*/i')
            ))
            ->sort('name','asc')
            ->limit(10)
            ->getQuery()
            ->execute();
        $results=array();
        foreach($taxons as $tax){
            $results[]=array(
                'level'=>$tax['level'],
                'name'=>$tax['name'],
                'label'=>$tax['label']
            );
        }
        $response=new Response(json_encode($results));
        $response->headers->set('Content-Type','application/json');
        return $response;
        exit;
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view/{level}-{taxon}",
     *      defaults={"page"=1, "sortby"="null", "sortorder"="null"},
     *      requirements={"level"="\d+"},
     *      name="_module_taxo_view"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view/{level}-{taxon}/page{page}",
     *      defaults={"sortby"="null", "sortorder"="null"},
     *      requirements={"level"="\d+", "page"="\d+"},
     *      name="_module_taxo_view_paginated"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view/{level}-{taxon}/page{page}/sort-{sortby}/order-{sortorder}",
     *      requirements={"level"="\d+", "page"="\d+", "sortby"="\w+", "sortorder"="null|asc|desc"},
     *      name="_module_taxo_view_paginated_sorted"
     *  )
     * @Method("get")
     * @Template()
     */
    public function module_taxo_viewAction($project,$collection,$module,$level,$taxon,$page,$sortby,$sortorder,Request $request)
    {
        $form_page=$request->query->get('form_page');
        if(!empty($form_page)){
            $page=$form_page;
        }
        if($this->container->get('request')->get('_route')=='_module_taxo_view_paginated'&&$page==1){
            return $this->redirect($this->generateUrl('_module_taxo_view',array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
                'level'=>$level,
                'taxon'=>$taxon
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
        $taxon=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->field('module')->references($module)
            ->field('name')->equals($taxon)
            ->field('level')->equals(intval($level))
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
        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->field('module')->references($module)
            ->field('taxonsrefs')->references($taxon);
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
        return $this->render('PlantnetDataBundle:Frontend\Module:taxo_view.html.twig',array(
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
            'current'=>'collection',
            'current_display'=>'grid'
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view_gallery/{level}-{taxon}",
     *      defaults={"page"=1},
     *      requirements={"level"="\d+"},
     *      name="_module_taxo_view_gallery"
     *  )
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view_gallery/{level}-{taxon}/page{page}",
     *      requirements={"level"="\d+", "page"="\d+"},
     *      name="_module_taxo_view_gallery_paginated"
     *  )
     * @Method("get")
     * @Template()
     */
    public function module_taxo_view_galleryAction($project,$collection,$module,$level,$taxon,$page,Request $request)
    {
        $form_page=$request->query->get('form_page');
        if(!empty($form_page)){
            $page=$form_page;
        }
        if($this->container->get('request')->get('_route')=='_module_taxo_view_gallery_paginated'&&$page==1){
            return $this->redirect($this->generateUrl('_module_taxo_view_gallery',array(
                'project'=>$project,
                'collection'=>$collection,
                'module'=>$module,
                'level'=>$level,
                'taxon'=>$taxon
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
        $taxon=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->field('module')->references($module)
            ->field('name')->equals($taxon)
            ->field('level')->equals(intval($level))
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
        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->hydrate(false)
            ->select('_id')
            ->field('module')->references($module)
            ->field('taxonsrefs')->references($taxon);
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
            $paginator->setCurrentPage($page);
        }
        catch(\Pagerfanta\Exception\NotValidCurrentPageException $e){
            throw $this->createNotFoundException('Page not found.');
        }
        //count to display
        $nb_images=1;
        $nb_locations=($taxon->getHaslocations())?1:0;
        return $this->render('PlantnetDataBundle:Frontend\Module:taxo_view.html.twig',array(
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
            'current'=>'collection',
            'current_display'=>'images'
        ));
    }

    /**
     * @Route(
     *      "/project/{project}/collection/{collection}/{module}/taxo_view_map/{level}-{taxon}",
     *      requirements={"level"="\d+"},
     *      name="_module_taxo_view_map"
     *  )
     * @Template()
     */
    public function module_taxo_view_mapAction($project,$collection,$module,$level,$taxon)
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
        if(!$module||$module->getType()!='text'){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $taxon=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->field('module')->references($module)
            ->field('name')->equals($taxon)
            ->field('level')->equals(intval($level))
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
        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->hydrate(false)
            ->select('_id')
            ->field('module')->references($module)
            ->field('taxonsrefs')->references($taxon);
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
        //count to display
        $nb_images=($taxon->getHasimages())?1:0;
        $nb_locations=1;
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
        return $this->render('PlantnetDataBundle:Frontend\Module:taxo_view.html.twig',array(
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
            'current'=>'collection',
            'current_display'=>'locations'
        ));
    }
}