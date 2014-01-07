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

    private function return_401_unauthorized()
    {
        $result=array(
            'code'=>'401',
            'message'=>'Unauthorized client'
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
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        $result['language']=$config->getDefaultlanguage();
        $result['original_project_url']=str_replace($this->get_prefix(),'',$config->getOriginaldb());
        //data1
        $result['project']=array(
        	'name'=>$config->getName(),
        	'url'=>$project,
        	'access_url'=>$this->get('router')->generate('front_project',array(
                'project'=>$project
            ),true),
            'hierarchy'=>array(
                'project'=>$project
            )
        );
        //data2
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        $result['collections']=array();
        foreach($collections as $collection){
        	if($collection->getDeleting()!=true){
	        	$result['collections'][]=array(
	        		'name'=>$collection->getName(),
	        		'url'=>$collection->getUrl(),
	        		'access_url'=>$this->get('router')->generate('front_collection',array(
		                'project'=>$project,
		                'collection'=>$collection->getUrl()
		            ),true),
	        		'description'=>($collection->getDescription())?$collection->getDescription():''
	        	);
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
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        $result['language']=$config->getDefaultlanguage();
        $result['original_project_url']=str_replace($this->get_prefix(),'',$config->getOriginaldb());
        //data1
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
            $this->return_404_not_found('Unable to find Collection entity.');
            exit;
        }
        $result['collection']=array(
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
        //data2
        $result['modules']=array();
        $modules=$collection->getModules();
        foreach($modules as $module){
        	if($module->getDeleting()!=true){
	        	$result['modules'][]=array(
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
	        		'public'=>($module->getWsonly())?false:true
	        	);
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
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        $result['module']=array(
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
        //data2
        $result['sub_modules_image']=array();
        $result['sub_modules_locality']=array();
        $result['sub_modules_other']=array();
        $children=$module->getChildren();
        foreach($children as $child){
        	if($child->getDeleting()!=true){
        		$child_tab=array(
        			'name'=>$child->getName(),
	        		'url'=>$child->getUrl(),
	        		'access_url'=>($child->getWsonly()||$child->getType()=='other')?null:$this->get('router')->generate('front_submodule',array(
		                'project'=>$project,
		                'collection'=>$collection->getUrl(),
		                'module'=>$module->getUrl(),
		                'submodule'=>$child->getUrl()
		            ),true),
	        		'row_count'=>$child->getNbrows(),
	        		'public'=>($child->getWsonly())?false:true
        		);
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
     *      {"name"="page", "dataType"="int", "required"=false, "description"="Page number (default = 1)"}
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
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        $result['module']=array(
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
        $result['pager']=array(
            'total_row_count'=>$paginator->getNbResults(),
            'max_rows_per_page'=>$paginator->getMaxPerPage(),
            'total_page_count'=>$paginator->getNbPages(),
            'current_page'=>$paginator->getCurrentPage(),
            'have_to_paginate'=>$paginator->haveToPaginate(),
            'previous_page'=>($paginator->haveToPaginate()&&$paginator->hasPreviousPage())?$paginator->getPreviousPage():null,
            'previous_page_url'=>($paginator->haveToPaginate()&&$paginator->hasPreviousPage())?$this->get('router')->generate('api_module_data_paginated',array(
                        'project'=>$project,
                        'collection'=>$collection->getUrl(),
                        'module'=>$module->getUrl(),
                        'page'=>$paginator->getPreviousPage()
                    ),true):null,
            'next_page'=>($paginator->haveToPaginate()&&$paginator->hasNextPage())?$paginator->getNextPage():null,
            'next_page_url'=>($paginator->haveToPaginate()&&$paginator->hasNextPage())?$this->get('router')->generate('api_module_data_paginated',array(
                        'project'=>$project,
                        'collection'=>$collection->getUrl(),
                        'module'=>$module->getUrl(),
                        'page'=>$paginator->getNextPage()
                    ),true):null,
        );
        $result['data']=array();
        $punits=$paginator->getCurrentPageResults();
        foreach($punits as $punit){
            $p=array();
            $attributes=$punit->getAttributes();
            foreach($field as $f){
                if(in_array($f->getId(),$display)){
                    $p[$f->getName()]=$attributes[$f->getId()];
                }
            }
            $result['data'][]=$p;
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
     *      {"name"="page", "dataType"="int", "required"=false, "description"="Page number (default = 1)"}
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
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        $result['module']=array(
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
        $result['pager']=array(
            'total_row_count'=>$paginator->getNbResults(),
            'max_rows_per_page'=>$paginator->getMaxPerPage(),
            'total_page_count'=>$paginator->getNbPages(),
            'current_page'=>$paginator->getCurrentPage(),
            'have_to_paginate'=>$paginator->haveToPaginate(),
            'previous_page'=>($paginator->haveToPaginate()&&$paginator->hasPreviousPage())?$paginator->getPreviousPage():null,
            'previous_page_url'=>($paginator->haveToPaginate()&&$paginator->hasPreviousPage())?$this->get('router')->generate('api_module_taxa_paginated',array(
                    'project'=>$project,
                    'collection'=>$collection->getUrl(),
                    'module'=>$module->getUrl(),
                    'page'=>$paginator->getPreviousPage()
                ),true):null,
            'next_page'=>($paginator->haveToPaginate()&&$paginator->hasNextPage())?$paginator->getNextPage():null,
            'next_page_url'=>($paginator->haveToPaginate()&&$paginator->hasNextPage())?$this->get('router')->generate('api_module_taxa_paginated',array(
                    'project'=>$project,
                    'collection'=>$collection->getUrl(),
                    'module'=>$module->getUrl(),
                    'page'=>$paginator->getNextPage()
                ),true):null,
        );
        $result['data']=array();
        $taxa=$paginator->getCurrentPageResults();
        foreach($taxa as $taxon){
            $result['data'][]=array(
                'identifier'=>$taxon->getIdentifier(),
                'level'=>$taxon->getLevel(),
                'label'=>$taxon->getLabel(),
                'name'=>$taxon->getName(),
                'parent_identifier'=>($taxon->getParent())?$taxon->getParent()->getIdentifier():null,
                'valid_identifier'=>($taxon->getIssynonym()&&$taxon->getChosen())?$taxon->getChosen()->getIdentifier():null,
            );
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
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        $result['submodule']=array(
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
            'public'=>($module->getWsonly())?false:true,
            'hierarchy'=>array(
                'project'=>$project,
                'collection'=>$collection->getUrl(),
                'module'=>$module->getUrl(),
                'submodule'=>$submodule->getUrl()
            )
        );
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
     *      {"name"="page", "dataType"="int", "required"=false, "description"="Page number (default = 1)"}
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
            ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
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
        $result['submodule']=array(
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
            'public'=>($module->getWsonly())?false:true,
            'hierarchy'=>array(
                'project'=>$project,
                'collection'=>$collection->getUrl(),
                'module'=>$module->getUrl(),
                'submodule'=>$submodule->getUrl()
            )
        );
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
                $result['pager']=array(
                    'total_row_count'=>$paginator->getNbResults(),
                    'max_rows_per_page'=>$paginator->getMaxPerPage(),
                    'total_page_count'=>$paginator->getNbPages(),
                    'current_page'=>$paginator->getCurrentPage(),
                    'have_to_paginate'=>$paginator->haveToPaginate(),
                    'previous_page'=>($paginator->haveToPaginate()&&$paginator->hasPreviousPage())?$paginator->getPreviousPage():null,
                    'previous_page_url'=>($paginator->haveToPaginate()&&$paginator->hasPreviousPage())?$this->get('router')->generate('api_submodule_data_paginated',array(
                            'project'=>$project,
                            'collection'=>$collection->getUrl(),
                            'module'=>$module->getUrl(),
                            'submodule'=>$submodule->getUrl(),
                            'page'=>$paginator->getPreviousPage()
                        ),true):null,
                    'next_page'=>($paginator->haveToPaginate()&&$paginator->hasNextPage())?$paginator->getNextPage():null,
                    'next_page_url'=>($paginator->haveToPaginate()&&$paginator->hasNextPage())?$this->get('router')->generate('api_submodule_data_paginated',array(
                            'project'=>$project,
                            'collection'=>$collection->getUrl(),
                            'module'=>$module->getUrl(),
                            'submodule'=>$submodule->getUrl(),
                            'page'=>$paginator->getNextPage()
                        ),true):null,
                );
                $img_dir=$this->get('router')->generate('front_index',array(),true).'uploads/'.$submodule->getUploaddir().'/';
                $result['data']=array();
                $images=$paginator->getCurrentPageResults();
                foreach($images as $image){
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
                    $p=array();
                    $p_attributes=$image->getPlantunit()->getAttributes();
                    foreach($field as $f){
                        if(in_array($f->getId(),$display)){
                            $p[$f->getName()]=$p_attributes[$f->getId()];
                        }
                    }
                    $i['punit']=$p;
                    $result['data'][]=$i;
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
                $result['pager']=array(
                    'total_row_count'=>$paginator->getNbResults(),
                    'max_rows_per_page'=>$paginator->getMaxPerPage(),
                    'total_page_count'=>$paginator->getNbPages(),
                    'current_page'=>$paginator->getCurrentPage(),
                    'have_to_paginate'=>$paginator->haveToPaginate(),
                    'previous_page'=>($paginator->haveToPaginate()&&$paginator->hasPreviousPage())?$paginator->getPreviousPage():null,
                    'previous_page_url'=>($paginator->haveToPaginate()&&$paginator->hasPreviousPage())?$this->get('router')->generate('api_submodule_data_paginated',array(
                            'project'=>$project,
                            'collection'=>$collection->getUrl(),
                            'module'=>$module->getUrl(),
                            'submodule'=>$submodule->getUrl(),
                            'page'=>$paginator->getPreviousPage()
                        ),true):null,
                    'next_page'=>($paginator->haveToPaginate()&&$paginator->hasNextPage())?$paginator->getNextPage():null,
                    'next_page_url'=>($paginator->haveToPaginate()&&$paginator->hasNextPage())?$this->get('router')->generate('api_submodule_data_paginated',array(
                            'project'=>$project,
                            'collection'=>$collection->getUrl(),
                            'module'=>$module->getUrl(),
                            'submodule'=>$submodule->getUrl(),
                            'page'=>$paginator->getNextPage()
                        ),true):null,
                );
                $result['data']=array();
                $locations=$paginator->getCurrentPageResults();
                foreach($locations as $location){
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
                    $p=array();
                    $p_attributes=$location->getPlantunit()->getAttributes();
                    foreach($field as $f){
                        if(in_array($f->getId(),$display)){
                            $p[$f->getName()]=$p_attributes[$f->getId()];
                        }
                    }
                    $l['punit']=$p;
                    $result['data'][]=$l;
                }
                break;
        }
        //response
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }
}