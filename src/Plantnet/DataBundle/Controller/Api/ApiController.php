<?php

namespace Plantnet\DataBundle\Controller\Api;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller,
	Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpFoundation\Request;

use Plantnet\DataBundle\Utils\StringHelp;
use Plantnet\DataBundle\Utils\ControllerHelp;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;


/**
 * Api controller.
 *
 * @Route("/api")
 */
class ApiController extends Controller
{
    private function get_prefix()
    {
        return $this->container->getParameter('mdb_base').'_';
    }

    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------
    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------
    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------

    private function check_authorized_client($config)
    {
        $ips=$config->getIps();
        $ips[]='127.0.0.1';
        if(!in_array($this->getRemoteIPAddress(),$ips)){
            $this->return_401_unauthorized();
            exit;
        }
    }

    private function getRemoteIPAddress()
    {
        if(!empty($_SERVER['HTTP_CLIENT_IP'])){
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){ 
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------
    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------
    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------

    private function return_401_unauthorized()
    {
        $result=array(
            'code'=>'401',
            'message'=>'Unauthorized client ('.$this->getRemoteIPAddress().')'
        );
        $response=new Response(json_encode($result));
        $response->setStatusCode(401);
        $response->headers->set('Content-Type','application/json');
        $response->send();
        exit;
    }

    private function return_404_not_found($message)
    {
        $result=array(
            'code'=>'404',
            'message'=>(!empty($message))?$message:'Resource not found'
        );
        $response=new Response(json_encode($result));
        $response->setStatusCode(404);
        $response->headers->set('Content-Type','application/json');
        $response->send();
        exit;
    }

    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------
    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------
    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------

    private function format_project($name,$project)
    {
        return array(
            'name'=>$name,
            'url'=>$project,
            'access_url'=>$this->get('router')->generate('front_project',array(
                'project'=>$project
            ),true),
            'hierarchy'=>array(
                'project'=>$project
            )
        );
    }

    private function format_collection($project,$collection)
    {
        return array(
            'name'=>$collection->getName(),
            'url'=>$collection->getUrl(),
            'access_url'=>$this->get('router')->generate('front_collection',array(
                'project'=>$project,
                'collection'=>$collection->getUrl()
            ),true),
            'description'=>($collection->getDescription())?$collection->getDescription():'',
            'hierarchy'=>array(
                'project'=>$project,
                'collection'=>$collection->getUrl()
            )
        );
    }

    private function format_module($project,$collection,$module)
    {
        return array(
            'name'=>$module->getName(),
            'url'=>$module->getUrl(),
            'access_url'=>($module->getWsonly())?null:$this->get('router')->generate('front_module',array(
                'project'=>$project,
                'collection'=>$collection->getUrl(),
                'module'=>$module->getUrl()
            ),true),
            'description'=>($module->getDescription())?$module->getDescription():'',
            'row_count'=>$module->getNbrows(),
            'has_taxonomy'=>($module->getTaxonomy())?true:false,
            'public'=>($module->getWsonly())?false:true,
            'hierarchy'=>array(
                'project'=>$project,
                'collection'=>$collection->getUrl(),
                'module'=>$module->getUrl()
            )
        );
    }

    private function format_submodule($project,$collection,$module,$submodule)
    {
        return array(
            'type'=>$submodule->getType(),
            'name'=>$submodule->getName(),
            'url'=>$submodule->getUrl(),
            'access_url'=>($submodule->getWsonly()||$submodule->getType()=='other')?null:$this->get('router')->generate('front_submodule',array(
                'project'=>$project,
                'collection'=>$collection->getUrl(),
                'module'=>$module->getUrl(),
                'submodule'=>$submodule->getUrl()
            ),true),
            'row_count'=>$submodule->getNbrows(),
            'public'=>($submodule->getWsonly())?false:true,
            'hierarchy'=>array(
                'project'=>$project,
                'collection'=>$collection->getUrl(),
                'module'=>$module->getUrl(),
                'submodule'=>$submodule->getUrl()
            )
        );
    }

    private function format_punit($field,$display,$punit)
    {
        $p=array(
            'identifier'=>$punit->getIdentifier(),
            'title1'=>$punit->getTitle1(),
            'title2'=>$punit->getTitle2(),
            'title3'=>$punit->getTitle3(),
        );
        $attributes=$punit->getAttributes();
        foreach($field as $f){
            if(in_array($f->getId(),$display)){
                $p[$f->getName()]=$attributes[$f->getId()];
            }
        }
        return $p;
    }

    private function format_taxon($taxon)
    {
        return array(
            'identifier'=>$taxon->getIdentifier(),
            'level'=>$taxon->getLevel(),
            'label'=>$taxon->getLabel(),
            'name'=>$taxon->getName(),
            'parent_identifier'=>($taxon->getParent())?$taxon->getParent()->getIdentifier():null,
            'parent_name'=>($taxon->getParent())?$taxon->getParent()->getName():null,
            'valid_identifier'=>($taxon->getIssynonym()&&$taxon->getChosen())?$taxon->getChosen()->getIdentifier():null,
            'valid_name'=>($taxon->getIssynonym()&&$taxon->getChosen())?$taxon->getChosen()->getName():null,
        );
    }

    private function format_image($field,$display,$field_sub,$img_dir,$image)
    {
        $i=array();
        $i_attributes=$image->getProperty();
        foreach($field_sub as $f){
            if($f->getDetails()==true){
                $i[$f->getName()]=$i_attributes[$f->getId()];
            }
        }
        $i['title1']=$image->getTitle1();
        $i['title2']=$image->getTitle2();
        $i['title3']=$image->getTitle3();
        $i['image_url']=$img_dir.$image->getPath();
        $i['punit']=$this->format_punit($field,$display,$image->getPlantunit());
        return $i;
    }

    private function format_location($field,$display,$field_sub,$location)
    {
        $l=array();
        $l_attributes=$location->getProperty();
        foreach($field_sub as $f){
            if($f->getDetails()==true){
                $l[$f->getName()]=$l_attributes[$f->getId()];
            }
        }
        $l['title1']=$location->getTitle1();
        $l['title2']=$location->getTitle2();
        $l['title3']=$location->getTitle3();
        $l['latitude']=$location->getLatitude();
        $l['longitude']=$location->getLongitude();
        $l['punit']=$this->format_punit($field,$display,$location->getPlantunit());
        return $l;
    }

    private function format_location_for_taxon($field,$display,$location)
    {
        $l=array();
        $l_attributes=$location->getProperty();
        $field_sub=$location->getModule()->getProperties();
        foreach($field_sub as $f){
            if($f->getDetails()==true){
                $l[$f->getName()]=$l_attributes[$f->getId()];
            }
        }
        $l['title1']=$location->getTitle1();
        $l['title2']=$location->getTitle2();
        $l['title3']=$location->getTitle3();
        $l['latitude']=$location->getLatitude();
        $l['longitude']=$location->getLongitude();
        $l['punit']=$this->format_punit($field,$display,$location->getPlantunit());
        return $l;
    }

    private function format_pager($paginator)
    {
        return array(
            'total_row_count'=>$paginator->getNbResults(),
            'max_rows_per_page'=>$paginator->getMaxPerPage(),
            'total_page_count'=>$paginator->getNbPages(),
            'current_page'=>$paginator->getCurrentPage(),
            'have_to_paginate'=>$paginator->haveToPaginate(),
            'previous_page'=>($paginator->haveToPaginate()&&$paginator->hasPreviousPage())?$paginator->getPreviousPage():null,
            'next_page'=>($paginator->haveToPaginate()&&$paginator->hasNextPage())?$paginator->getNextPage():null
        );
    }

    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------
    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------
    // ------- ------- ------- ------- ------- ------- ------- ------- ------- -------

    /**
     * @ApiDoc(
     *	section="Publish v2 - 01. Project entity",
     *	description="Describes a project and lists its collections",
     *	statusCodes={
     *		200="Returned when: Successful",
     *		401="Returned when: Unauthorized client",
     *		404="Returned when: Resource not found"
	 *	},
	 *	filters={
	 *		{"name"="project", "dataType"="String", "required"=true, "description"="Project url"}
	 *	}
     * )
     *
     * @Route(
     *		"/{project}",
     *		name="api_project_detail"
     * )
     * @Method("get")
     */
    public function api_project_detailAction($project)
    {
        //check project
        try{
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this,$this->container);
        }
        catch(\Exception $e){
            $this->return_404_not_found($e->getMessage());
            exit;
        }
        //init
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //get language config
        $config=ControllerHelp::get_config($project,$dm,$this);
        $this->check_authorized_client($config);
        $result['language']=$config->getDefaultlanguage();
        $result['original_project_url']=str_replace($this->get_prefix(),'',$config->getOriginaldb());
        //data1
        $result['project']=$this->format_project($config->getName(),$project);
        //data2
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        $result['collections']=array();
        foreach($collections as $collection){
        	if($collection->getDeleting()!=true){
	        	$result['collections'][]=$this->format_collection($project,$collection);
        	}
        }
        //response
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    /**
     * @ApiDoc(
     *	section="Publish v2 - 02. Collection entity",
     *	description="Describes a collection and lists its modules",
     *	statusCodes={
     *		200="Returned when: Successful",
     *		401="Returned when: Unauthorized client",
     *		404="Returned when: Resource not found"
	 *	},
	 *	filters={
	 *		{"name"="project", "dataType"="String", "required"=true, "description"="Project url"},
	 *		{"name"="collection", "dataType"="String", "required"=true, "description"="Collection url"}
	 *	}
     * )
     *
     * @Route(
     *		"/{project}/{collection}",
     *		name="api_collection_detail"
     * )
     * @Method("get")
     */
    public function api_collection_detailAction($project,$collection)
    {
        //check project
        try{
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this,$this->container);
        }
        catch(\Exception $e){
            $this->return_404_not_found($e->getMessage());
            exit;
        }
        //init
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //get language config
        $config=ControllerHelp::get_config($project,$dm,$this);
        $this->check_authorized_client($config);
        $result['language']=$config->getDefaultlanguage();
        $result['original_project_url']=str_replace($this->get_prefix(),'',$config->getOriginaldb());
        //data1
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
            $this->return_404_not_found('Unable to find Collection entity.');
            exit;
        }
        $result['collection']=$this->format_collection($project,$collection);
        //data2
        $result['modules']=array();
        $modules=$collection->getModules();
        foreach($modules as $module){
        	if($module->getDeleting()!=true){
	        	$result['modules'][]=$this->format_module($project,$collection,$module);
        	}
        }
        //response
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    /**
     * @ApiDoc(
     *	section="Publish v2 - 03. Module entity",
     *	description="Describes a module and lists its sub-modules",
     *	statusCodes={
     *		200="Returned when: Successful",
     *		401="Returned when: Unauthorized client",
     *		404="Returned when: Resource not found"
	 *	},
	 *	filters={
	 *		{"name"="project", "dataType"="String", "required"=true, "description"="Project url"},
	 *		{"name"="collection", "dataType"="String", "required"=true, "description"="Collection url"},
	 *		{"name"="module", "dataType"="String", "required"=true, "description"="Module url"}
	 *	}
     * )
     *
     * @Route(
     *		"/{project}/{collection}/{module}",
     *		name="api_module_detail"
     * )
     * @Method("get")
     */
    public function api_module_detailAction($project,$collection,$module)
    {
        //check project
        try{
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this,$this->container);
        }
        catch(\Exception $e){
            $this->return_404_not_found($e->getMessage());
            exit;
        }
        //init
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //get language config
        $config=ControllerHelp::get_config($project,$dm,$this);
        $this->check_authorized_client($config);
        $result['language']=$config->getDefaultlanguage();
        $result['original_project_url']=str_replace($this->get_prefix(),'',$config->getOriginaldb());
        //data1
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
        	$this->return_404_not_found('Unable to find Collection entity.');
            exit;
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='text'||$module->getDeleting()==true){
            $this->return_404_not_found('Unable to find Module entity.');
            exit;
        }
        $result['module']=$this->format_module($project,$collection,$module);
        //data2
        $result['sub_modules_image']=array();
        $result['sub_modules_locality']=array();
        $result['sub_modules_other']=array();
        $children=$module->getChildren();
        foreach($children as $child){
        	if($child->getDeleting()!=true){
        		$child_tab=$this->format_submodule($project,$collection,$module,$child);
        		switch($child->getType()){
        			case 'image':
        				$result['sub_modules_image'][]=$child_tab;
        				break;
        			case 'locality':
        				$result['sub_modules_locality'][]=$child_tab;
        				break;
        			case 'other':
        				$result['sub_modules_other'][]=$child_tab;
        				break;
        		}
        	}
        }
        //response
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    /**
     * @ApiDoc(
     *  section="Publish v2 - 03. Module entity",
     *  description="Describes a module and lists its data",
     *  statusCodes={
     *      200="Returned when: Successful",
     *      401="Returned when: Unauthorized client",
     *      404="Returned when: Resource not found"
     *  },
     *  filters={
     *      {"name"="project", "dataType"="String", "required"=true, "description"="Project url"},
     *      {"name"="collection", "dataType"="String", "required"=true, "description"="Collection url"},
     *      {"name"="module", "dataType"="String", "required"=true, "description"="Module url"},
     *      {"name"="page", "dataType"="int", "required"=false, "description"="Page number", "default"="1"}
     *  }
     * )
     *
     * @Route(
     *      "/{project}/{collection}/{module}/data",
     *      defaults={"page"=1},
     *      name="api_module_data"
     * )
     * @Route(
     *      "/{project}/{collection}/{module}/data/page{page}",
     *      requirements={"page"="\d+"},
     *      name="api_module_data_paginated"
     * )
     * @Method("get")
     */
    public function api_module_dataAction($project,$collection,$module,$page)
    {
        //check project
        try{
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this,$this->container);
        }
        catch(\Exception $e){
            $this->return_404_not_found($e->getMessage());
            exit;
        }
        //init
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //get language config
        $config=ControllerHelp::get_config($project,$dm,$this);
        $this->check_authorized_client($config);
        $result['language']=$config->getDefaultlanguage();
        $result['original_project_url']=str_replace($this->get_prefix(),'',$config->getOriginaldb());
        //data1
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
            $this->return_404_not_found('Unable to find Collection entity.');
            exit;
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='text'||$module->getDeleting()==true){
            $this->return_404_not_found('Unable to find Module entity.');
            exit;
        }
        $result['module']=$this->format_module($project,$collection,$module);
        //data2
        $display=array();
        $order=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getDetails()==true){
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
        catch(\Exception $e){
            $this->return_404_not_found('Page "'.$page.'" not found.');
            exit;
        }
        $result['pager']=$this->format_pager($paginator);
        $result['data']=array();
        $punits=$paginator->getCurrentPageResults();
        foreach($punits as $punit){
            $result['data'][]=$this->format_punit($field,$display,$punit);
        }
        //response
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    /**
     * @ApiDoc(
     *  section="Publish v2 - 03. Module entity",
     *  description="Describes a module and lists its taxa",
     *  statusCodes={
     *      200="Returned when: Successful",
     *      401="Returned when: Unauthorized client",
     *      404="Returned when: Resource not found"
     *  },
     *  filters={
     *      {"name"="project", "dataType"="String", "required"=true, "description"="Project url"},
     *      {"name"="collection", "dataType"="String", "required"=true, "description"="Collection url"},
     *      {"name"="module", "dataType"="String", "required"=true, "description"="Module url"},
     *      {"name"="page", "dataType"="int", "required"=false, "description"="Page number", "default"="1"}
     *  }
     * )
     *
     * @Route(
     *      "/{project}/{collection}/{module}/taxa",
     *      defaults={"page"=1},
     *      name="api_module_taxa"
     * )
     * @Route(
     *      "/{project}/{collection}/{module}/taxa/page{page}",
     *      requirements={"page"="\d+"},
     *      name="api_module_taxa_paginated"
     * )
     * @Method("get")
     */
    public function api_module_taxaAction($project,$collection,$module,$page)
    {
        //check project
        try{
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this,$this->container);
        }
        catch(\Exception $e){
            $this->return_404_not_found($e->getMessage());
            exit;
        }
        //init
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //get language config
        $config=ControllerHelp::get_config($project,$dm,$this);
        $this->check_authorized_client($config);
        $result['language']=$config->getDefaultlanguage();
        $result['original_project_url']=str_replace($this->get_prefix(),'',$config->getOriginaldb());
        //data1
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
            $this->return_404_not_found('Unable to find Collection entity.');
            exit;
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='text'||$module->getDeleting()==true){
            $this->return_404_not_found('Unable to find Module entity.');
            exit;
        }
        if(!$module->getTaxonomy()){
            $this->return_404_not_found('Taxonomy is not enabled for this module.');
            exit;
        }
        $result['module']=$this->format_module($project,$collection,$module);
        //data2
        $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->field('module')->references($module)
            ->sort('level','asc')
            ->sort('name','asc');
        $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
        try{
            $paginator->setMaxPerPage(50);
            $paginator->setCurrentPage($page);
        }
        catch(\Exception $e){
            $this->return_404_not_found('Page "'.$page.'" not found.');
            exit;
        }
        $result['pager']=$this->format_pager($paginator);
        $result['data']=array();
        $taxa=$paginator->getCurrentPageResults();
        foreach($taxa as $taxon){
            $result['data'][]=$this->format_taxon($taxon);
        }
        //response
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    /**
     * @ApiDoc(
     *  section="Publish v2 - 04. Sub-module entity",
     *  description="Describes a sub-module",
     *  statusCodes={
     *      200="Returned when: Successful",
     *      401="Returned when: Unauthorized client",
     *      404="Returned when: Resource not found"
     *  },
     *  filters={
     *      {"name"="project", "dataType"="String", "required"=true, "description"="Project url"},
     *      {"name"="collection", "dataType"="String", "required"=true, "description"="Collection url"},
     *      {"name"="module", "dataType"="String", "required"=true, "description"="Module url"},
     *      {"name"="submodule", "dataType"="String", "required"=true, "description"="Sub-Module url"}
     *  }
     * )
     *
     * @Route(
     *      "/{project}/{collection}/{module}/{submodule}",
     *      name="api_submodule_detail"
     * )
     * @Method("get")
     */
    public function api_submodule_detailAction($project,$collection,$module,$submodule)
    {
        //check project
        try{
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this,$this->container);
        }
        catch(\Exception $e){
            $this->return_404_not_found($e->getMessage());
            exit;
        }
        //init
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //get language config
        $config=ControllerHelp::get_config($project,$dm,$this);
        $this->check_authorized_client($config);
        $result['language']=$config->getDefaultlanguage();
        $result['original_project_url']=str_replace($this->get_prefix(),'',$config->getOriginaldb());
        //data1
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
            $this->return_404_not_found('Unable to find Collection entity.');
            exit;
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='text'||$module->getDeleting()==true){
            $this->return_404_not_found('Unable to find Module entity.');
            exit;
        }
        $submodule=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$submodule,
                'parent.id'=>$module->getId(),
                'collection.id'=>$collection->getId()
            ));
        if(!$submodule||$submodule->getType()=='text'||$submodule->getDeleting()==true){
            $this->return_404_not_found('Unable to find Sub-module entity.');
            exit;
        }
        $result['submodule']=$this->format_submodule($project,$collection,$module,$submodule);
        //data2
        //response
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    /**
     * @ApiDoc(
     *  section="Publish v2 - 04. Sub-module entity",
     *  description="Describes a sub-module and lists its data",
     *  statusCodes={
     *      200="Returned when: Successful",
     *      401="Returned when: Unauthorized client",
     *      404="Returned when: Resource not found"
     *  },
     *  filters={
     *      {"name"="project", "dataType"="String", "required"=true, "description"="Project url"},
     *      {"name"="collection", "dataType"="String", "required"=true, "description"="Collection url"},
     *      {"name"="module", "dataType"="String", "required"=true, "description"="Module url"},
     *      {"name"="submodule", "dataType"="String", "required"=true, "description"="Sub-Module url"},
     *      {"name"="page", "dataType"="int", "required"=false, "description"="Page number", "default"="1"}
     *  }
     * )
     *
     * @Route(
     *      "/{project}/{collection}/{module}/{submodule}/data",
     *      defaults={"page"=1},
     *      name="api_submodule_data"
     * )
     * @Route(
     *      "/{project}/{collection}/{module}/{submodule}/data/page{page}",
     *      requirements={"page"="\d+"},
     *      name="api_submodule_data_paginated"
     * )
     * @Method("get")
     */
    public function api_submodule_dataAction($project,$collection,$module,$submodule,$page)
    {
        //check project
        try{
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this,$this->container);
        }
        catch(\Exception $e){
            $this->return_404_not_found($e->getMessage());
            exit;
        }
        //init
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //get language config
        $config=ControllerHelp::get_config($project,$dm,$this);
        $this->check_authorized_client($config);
        $result['language']=$config->getDefaultlanguage();
        $result['original_project_url']=str_replace($this->get_prefix(),'',$config->getOriginaldb());
        //data1
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
            $this->return_404_not_found('Unable to find Collection entity.');
            exit;
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='text'||$module->getDeleting()==true){
            $this->return_404_not_found('Unable to find Module entity.');
            exit;
        }
        $submodule=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$submodule,
                'parent.id'=>$module->getId(),
                'collection.id'=>$collection->getId()
            ));
        if(!$submodule||$submodule->getType()=='text'||$submodule->getDeleting()==true){
            $this->return_404_not_found('Unable to find Sub-module entity.');
            exit;
        }
        $result['submodule']=$this->format_submodule($project,$collection,$module,$submodule);
        //data2
        $display=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getMain()==true){
                $display[]=$row->getId();
            }
        }
        $field_sub=$submodule->getProperties();
        switch($submodule->getType()){
            case 'image':
                $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->field('module')->references($submodule)
                    ->sort('title1','asc')
                    ->sort('title2','asc');
                $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                try{
                    $paginator->setMaxPerPage(50);
                    $paginator->setCurrentPage($page);
                }
                catch(\Exception $e){
                    $this->return_404_not_found('Page "'.$page.'" not found.');
                    exit;
                }
                $result['pager']=$this->format_pager($paginator);
                $img_dir=$this->get('router')->generate('front_index',array(),true).'uploads/'.$submodule->getUploaddir().'/';
                $result['data']=array();
                $images=$paginator->getCurrentPageResults();
                foreach($images as $image){
                    $result['data'][]=$this->format_image($field,$display,$field_sub,$img_dir,$image);
                }
                break;
            case 'locality':
                $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                    ->field('module')->references($submodule)
                    ->sort('title1','asc')
                    ->sort('title2','asc');
                $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                try{
                    $paginator->setMaxPerPage(50);
                    $paginator->setCurrentPage($page);
                }
                catch(\Exception $e){
                    $this->return_404_not_found('Page "'.$page.'" not found.');
                    exit;
                }
                $result['pager']=$this->format_pager($paginator);
                $result['data']=array();
                $locations=$paginator->getCurrentPageResults();
                foreach($locations as $location){
                    $result['data'][]=$this->format_location($field,$display,$field_sub,$location);
                }
                break;
        }
        //response
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    /**
     * @ApiDoc(
     *  section="Publish v2 - 05. Taxon entity",
     *  description="Describes a taxon and lists its locations",
     *  statusCodes={
     *      200="Returned when: Successful",
     *      401="Returned when: Unauthorized client",
     *      404="Returned when: Resource not found"
     *  },
     *  filters={
     *      {"name"="project", "dataType"="String", "required"=true, "description"="Project url"},
     *      {"name"="collection", "dataType"="String", "required"=true, "description"="Collection url"},
     *      {"name"="module", "dataType"="String", "required"=true, "description"="Module url"},
     *      {"name"="taxon", "dataType"="String", "required"=true, "description"="Taxon name"},
     *      {"name"="page", "dataType"="int", "required"=false, "description"="Page number", "default"="1"}
     *  }
     * )
     *
     * @Route(
     *      "/{project}/{collection}/{module}/taxon_name/{taxon}/locations",
     *      defaults={"page"=1},
     *      name="api_module_taxon_name"
     * )
     * @Route(
     *      "/{project}/{collection}/{module}/taxon_name/{taxon}/locations/page{page}",
     *      requirements={"page"="\d+"},
     *      name="api_module_taxon_name_paginated"
     * )
     * @Method("get")
     */
    public function api_module_taxon_name_locationsAction($project,$collection,$module,$taxon,$page)
    {
        $result=$this->api_module_taxon_locations($project,$collection,$module,$taxon,$page,'name');
        //response
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    /**
     * @ApiDoc(
     *  section="Publish v2 - 05. Taxon entity",
     *  description="Describes a taxon and lists its locations",
     *  statusCodes={
     *      200="Returned when: Successful",
     *      401="Returned when: Unauthorized client",
     *      404="Returned when: Resource not found"
     *  },
     *  filters={
     *      {"name"="project", "dataType"="String", "required"=true, "description"="Project url"},
     *      {"name"="collection", "dataType"="String", "required"=true, "description"="Collection url"},
     *      {"name"="module", "dataType"="String", "required"=true, "description"="Module url"},
     *      {"name"="taxon", "dataType"="String", "required"=true, "description"="Taxon identifier"},
     *      {"name"="page", "dataType"="int", "required"=false, "description"="Page number", "default"="1"}
     *  }
     * )
     *
     * @Route(
     *      "/{project}/{collection}/{module}/taxon_identifier/{taxon}/locations",
     *      defaults={"page"=1},
     *      name="api_module_taxon_identifier"
     * )
     * @Route(
     *      "/{project}/{collection}/{module}/taxon_identifier/{taxon}/locations/page{page}",
     *      requirements={"page"="\d+"},
     *      name="api_module_taxon_identifier_paginated"
     * )
     * @Method("get")
     */
    public function api_module_taxon_identifier_locationsAction($project,$collection,$module,$taxon,$page)
    {
        $result=$this->api_module_taxon_locations($project,$collection,$module,$taxon,$page,'identifier');
        //response
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    private function api_module_taxon_locations($project,$collection,$module,$taxon,$page,$param)
    {
        //check project
        try{
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this,$this->container);
        }
        catch(\Exception $e){
            $this->return_404_not_found($e->getMessage());
            exit;
        }
        //init
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //get language config
        $config=ControllerHelp::get_config($project,$dm,$this);
        $this->check_authorized_client($config);
        $result['language']=$config->getDefaultlanguage();
        $result['original_project_url']=str_replace($this->get_prefix(),'',$config->getOriginaldb());
        //data1
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
            $this->return_404_not_found('Unable to find Collection entity.');
            exit;
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='text'||$module->getDeleting()==true){
            $this->return_404_not_found('Unable to find Module entity.');
            exit;
        }
        if(!$module->getTaxonomy()){
            $this->return_404_not_found('Taxonomy is not enabled for this module.');
            exit;
        }
        $taxons=$dm->getRepository('PlantnetDataBundle:Taxon')
            ->findBy(array(
                $param=>$taxon,
                'module.id'=>$module->getId()
            ));
        if(count($taxons)==0){
            $this->return_404_not_found('Unable to find Taxon entity.');
            exit;
        }
        if(count($taxons)>1){
            $this->return_404_not_found('This name references more than one taxon.');
            exit;
        }
        $taxon=null;
        foreach($taxons as $tax){
            $taxon=$tax;
        }
        if(!$taxon){
            $this->return_404_not_found('Unable to find Taxon entity.');
            exit;
        }
        $result['taxon']=$this->format_taxon($taxon);
        //data2
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
        $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Location')
            ->field('plantunit.$id')->in($pu_ids)
            ->sort('title1','asc')
            ->sort('title2','asc');
        $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
        try{
            $paginator->setMaxPerPage(50);
            $paginator->setCurrentPage($page);
        }
        catch(\Exception $e){
            $this->return_404_not_found('Page "'.$page.'" not found.');
            exit;
        }
        $result['pager']=$this->format_pager($paginator);
        $result['data']=array();
        $locations=$paginator->getCurrentPageResults();
        foreach($locations as $location){
            $result['data'][]=$this->format_location_for_taxon($field,$display,$location);
        }
        //
        return $result;
    }
    
    /**
     * @ApiDoc(
     *  section="Publish v2 - GeoData. Sub-module entity [type = locality]",
     *  description="Returns geo data from a 'locality' sub-module entity [GeoJson]",
     *  statusCodes={
     *      200="Returned when: Successful",
     *      401="Returned when: Unauthorized client",
     *      404="Returned when: Resource not found"
     *  },
     *  filters={
     *      {"name"="project", "dataType"="String", "required"=true, "description"="Project url"},
     *      {"name"="collection", "dataType"="String", "required"=true, "description"="Collection url"},
     *      {"name"="module", "dataType"="String", "required"=true, "description"="Module url"},
     *      {"name"="submodule", "dataType"="String", "required"=true, "description"="'Location' Sub-Module url"}
     *  }
     * )
     *
     * @Route(
     *      "/{project}/{collection}/{module}/{submodule}/geodata",
     *      name="api_submodule_geodata"
     * )
     * @Method("get")
     */
    public function api_submodule_geodataAction($project,$collection,$module,$submodule)
    {
        ini_set('memory_limit','-1');
        //check project
        try{
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this,$this->container);
        }
        catch(\Exception $e){
            $this->return_404_not_found($e->getMessage());
            exit;
        }
        //init
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //get language config
        $config=ControllerHelp::get_config($project,$dm,$this);
        $this->check_authorized_client($config);
        //data1
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
            $this->return_404_not_found('Unable to find Collection entity.');
            exit;
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='text'||$module->getDeleting()==true){
            $this->return_404_not_found('Unable to find Module entity.');
            exit;
        }
        $submodule=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$submodule,
                'parent.id'=>$module->getId(),
                'collection.id'=>$collection->getId()
            ));
        if(!$submodule||$submodule->getType()=='text'||$submodule->getDeleting()==true){
            $this->return_404_not_found('Unable to find Sub-module entity.');
            exit;
        }
        if($submodule->getType()!='locality'){
            $this->return_404_not_found('Unable to find geo data for this Sub-module entity.');
            exit;
        }
        //data2
        $display=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getMain()==true){
                $display[]=$row->getId();
            }
        }
        $field_sub=$submodule->getProperties();
        $field_sub_tab=array();
        foreach($field_sub as $f){
            if($f->getDetails()==true){
                $field_sub_tab[$f->getId()]=StringHelp::cleanToKey($f->getName());
            }
        }
        \MongoCursor::$timeout=-1;
        $locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
            ->hydrate(false)
            ->select('title1')
            ->select('title2')
            ->select('title3')
            ->select('property')
            ->select('latitude')
            ->select('longitude')
            ->select('idparent')
            ->field('module')->references($submodule)
            ->getQuery()
            ->execute()
            ->toArray();
        array_walk($locations,function (&$item,$key,$field_sub_tab){
            $l=array(
                'type'=>'Feature',
                'geometry'=>array(
                    'type'=>'Point',
                    'coordinates'=>array(
                        $item['longitude'],
                        $item['latitude']
                    )
                ),
                'properties'=>array(
                    'title1'=>$item['title1'],
                    'title2'=>$item['title2'],
                    'title3'=>$item['title3'],
                    'parent_identifier'=>$item['idparent']
                )
            );
            foreach($item['property'] as $key=>$value){
                if(array_key_exists($key,$field_sub_tab)){
                    $l['properties'][$field_sub_tab[$key]]=$value;
                }
            }
            $item=$l;
        },$field_sub_tab);
        $locations=array_values($locations);
        //response
        $response=new Response(json_encode(array(
            'type'=>'FeatureCollection',
            'features'=>$locations
        )));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }
}