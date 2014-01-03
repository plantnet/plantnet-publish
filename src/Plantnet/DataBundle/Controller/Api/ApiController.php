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

    /**
     * @ApiDoc(
     *	section="Publish v2",
     *	description="Describes a project and lists its collections",
     *	statusCodes={
     *		200="Returned when: Successful",
     *		401="Returned whan: Unauthorized client",
     *		404="Returned when: Resource not found"
	 *	},
	 *	filters={
	 *		{"name"="project", "dataType"="String", "required"=true, "description"="Project url"}
	 *	}
     * )
     *
     * @Route(
     *		"/{project}",
     *		name="api_project_detail",
     *		requirements={"project"="\w+"}
     * )
     * @Method("get")
     */
    public function api_project_detailAction($project)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //
        $config=ControllerHelp::get_config($project,$dm,$this);
        $result['project']=array(
        	'name'=>$config->getName(),
        	'url'=>$project,
        	'access_url'=>$this->get('router')->generate('front_project',array(
                'project'=>$project
            ),true)
        );
        //
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
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    /**
     * @ApiDoc(
     *	section="Publish v2",
     *	description="Describes a collection and lists its modules",
     *	statusCodes={
     *		200="Returned when: Successful",
     *		401="Returned whan: Unauthorized client",
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
     *		name="api_collection_detail",
     *		requirements={"project"="\w+","collection"="\w+"}
     * )
     * @Method("get")
     */
    public function api_collection_detailAction($project,$collection)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
        	throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $result['collection']=array(
        	'name'=>$collection->getName(),
	        'url'=>$collection->getUrl(),
	        'access_url'=>$this->get('router')->generate('front_collection',array(
                'project'=>$project,
                'collection'=>$collection->getUrl()
            ),true),
	        'description'=>($collection->getDescription())?$collection->getDescription():''
        );
        //
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
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }

    /**
     * @ApiDoc(
     *	section="Publish v2",
     *	description="Describes a module and lists its sub-modules",
     *	statusCodes={
     *		200="Returned when: Successful",
     *		401="Returned whan: Unauthorized client",
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
     *		name="api_module_detail",
     *		requirements={"project"="\w+","collection"="\w+","module"="\w+"}
     * )
     * @Method("get")
     */
    public function api_module_detailAction($project,$collection,$module)
    {
        ControllerHelp::check_enable_project($project,$this->get_prefix(),$this);
        //
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $result=array();
        //
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array('url'=>$collection));
        if(!$collection||$collection->getDeleting()==true){
        	throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module||$module->getType()!='text'||$module->getDeleting()==true){
            throw $this->createNotFoundException('Unable to find Module entity.');
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
    		'public'=>($module->getWsonly())?false:true
        );
        //
        $result['sub_modules_image']=array();
        $result['sub_modules_location']=array();
        $result['sub_modules_other']=array();
        $children=$module->getChildren();
        foreach($children as $child){
        	if($child->getDeleting()!=true){
        		$child_tab=array(
        			'name'=>$child->getName(),
	        		'url'=>$child->getUrl(),
	        		'access_url'=>($module->getWsonly())?null:$this->get('router')->generate('front_submodule',array(
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
        				$result['sub_modules_location'][]=$child_tab;
        				break;
        			case 'other':
        				$result['sub_modules_other'][]=$child_tab;
        				break;
        		}
        	}
        }
        $response=new Response(json_encode($result));
        $response->headers->set('Content-Type','application/json');
        return $response;
    }
}