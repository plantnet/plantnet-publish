<?php

namespace Plantnet\DataBundle\Controller\Frontend;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller,
	Symfony\Component\HttpFoundation\Response;

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
        return $this->render('PlantnetDataBundle:Frontend:index.html.twig', array(
            'projects' => $projects,
            'current' => 'index'
        ));
    }

    /**
     * @Route("/project/{project}", name="_project")
     * @Template()
     */
    public function projectAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $coll = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        return $this->render('PlantnetDataBundle:Frontend:project.html.twig', array(
            'project' => $project,
            'collection' => $coll,
            'current' => 'project'
        ));
    }

    public function menuCollectionListAction($project)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collections = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        return $this->render('PlantnetDataBundle:Frontend:menuCollectionList.html.twig', array(
            'project' => $project,
            'collections' => $collections
        ));
    }

    /**
     * @Route("/project/{project}/collection/{collection}", name="_collection")
     * @Template()
     */
    public function collectionAction($project, $collection)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $coll = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        return $this->render('PlantnetDataBundle:Frontend:collection.html.twig', array(
            'project' => $project,
            'collection' => $coll,
            'current' => 'collection'
        ));
    }

    /**
     * @Route("/project/{project}/collection/{collection}/{module}", name="_module")
     * @Template()
     */
    public function moduleAction($project, $collection, $module)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        $mod = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name'=> $module, 'collection.id' => $collection->getId()));
        if($mod->getParent()){
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($mod->getParent()->getId());
        }else{
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($mod->getId());
        }
        $display = array();
        $field = $mod->getProperties();
        foreach($field as $row){
            if($row->getMain() == true){
                $display[] = $row->getName();
            }
        }
        switch ($mod->getType())
        {
            case 'text':
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->field('module')->references($mod)
                    ->hydrate(false);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Frontend:datagrid.html.twig', array(
                    'project' => $project,
                    'paginator' => $paginator,
                    'field' => $field,
                    'collection' => $collection,
                    'module' => $module,
                    'type' => 'table',
                    'display' => $display
                ));
                break;
            case 'image':
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->field('module')->references($mod)
                    ->hydrate(false);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(20);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Frontend:gallery.html.twig', array(
                    'project' => $project,
                    'paginator' => $paginator,
                    'collection' => $collection,
                    'module' => $mod,
                    'type' => 'images'
                ));
                break;
            case 'locality':
                $db=$this->get_prefix().$project;
                $m=new \Mongo();
                $c_plantunits=$m->$db->Plantunit->find(
                    array('module.$id'=>new \MongoId($module->getId())),
                    array('_id'=>1)
                );
                $id_plantunits=array();
                foreach($c_plantunits as $id=>$p)
                {
                    $id_plantunits[]=new \MongoId($id);
                }
                unset($c_plantunits);
                $locations=array();
                $c_locations=$m->$db->Location->find(
                    array(
                        'plantunit.$id'=>array('$in'=>$id_plantunits),
                        'module.$id'=>new \MongoId($mod->getId())
                    ),
                    array('_id'=>1,'latitude'=>1,'longitude'=>1,'title1'=>1,'title2'=>1)
                );
                unset($id_plantunits);
                foreach($c_locations as $id=>$l)
                {
                    $loc=array();
                    $loc['id']=$id;
                    $loc['latitude']=$l['latitude'];
                    $loc['longitude']=$l['longitude'];
                    $loc['title1']=$l['title1'];
                    $loc['title2']=$l['title2'];
                    $locations[]=$loc;
                }
                unset($c_locations);
                unset($m);
                $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                return $this->render('PlantnetDataBundle:Frontend:map.html.twig',array(
                    'project' => $project,
                    'collection' => $collection,
                    'module' => $mod,
                    'type' => 'localisation',
                    'locations' => $locations,
                    'layers' => $layers
                ));
                break;
        }
    }

    /**
     * @Route("/project/{project}/collection/{collection}/{module}/details/{id}", name="_details")
     * @Template()
     */
    public function detailsAction($project, $collection, $module, $id)
    {
        $projects=$this->database_list();
        if(!in_array($project,$projects))
        {
            throw $this->createNotFoundException('Unable to find Project "'.$project.'".');
        }
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->get_prefix().$project);
        $coll = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('name' => $module, 'collection.id' => $coll->getId()));
        $display = array();
        $field = $module->getProperties();
        foreach($field as $row){
            if($row->getDetails() == true){
                $display[] = $row->getName();
            }
        }
        $plantunit = $dm->getRepository('PlantnetDataBundle:Plantunit')
            ->findOneBy(array('module.id' => $module->getId(), 'id' => $id));
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
        return $this->render('PlantnetDataBundle:Frontend:details.html.twig', array(
            'idplantunit' => $plantunit->getId(),
            'project' => $project,
            'display' => $display,
            'layers' => $layers,
            'collection' => $coll,
            'module' => $module,
            'plantunit' => $plantunit
        ));
    }















    

    

    

    

    /**
     * @Route("/search", name="_search")
     * @Template()
     */
    public function searchAction()
    {
        $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
        $layers=new \SimpleXMLElement($dir.'layers_search.xml',0,true);
        return $this->render('PlantnetDataBundle:Frontend:search.html.twig', array(
            'layers' => $layers,
            'current' => 'taxonomy'
        ));
    }

    /**
     * @Route("/result", name="_result")
     * @Method("post")
     * @Template()
     */
    public function resultAction()
    {
        $request=$this->getRequest();
        if('POST'===$request->getMethod())
        {
            $data=$request->request->all();
            $dm = $this->get('doctrine.odm.mongodb.document_manager');
            $plantunits=array();
            $ids=array();
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
                    $ids[]=$location['plantunit']['$id']->{'$id'};
                }
            }
            // $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            //     ->field('_id')->in($ids);
            // $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($plantunits));
            // $paginator->setMaxPerPage(50);
            // $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
            $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->field('_id')->in($ids)
                ->getQuery()
                ->execute();
            return $this->render('PlantnetDataBundle:Frontend:result.html.twig', array(
                'current' => 'taxonomy',
                // 'paginator' => $paginator,
                'plantunits' => $plantunits
            ));
        }
        else
        {
            return $this->redirect($this->generateUrl('_search'));
        }
        return $this->render('PlantnetDataBundle:Frontend:result.html.twig', array(
            'current' => 'taxonomy'
        ));
    }









    /*
    public function listAction()
    {
            $dm = $this->get('doctrine.odm.mongodb.document_manager');

            $collections = $dm->getRepository('PlantnetDataBundle:Collection')
                                ->findAll();
            $list = array();
        foreach($collections as $collection){
            $coll = array('collection'=>$collection->getName());

            $module = $dm->getRepository('PlantnetDataBundle:Module')
                                ->findBy(array('collection' => $collection->getId()));
            array_push($coll, $module);
            array_push($list, $coll);
        }


            return $this->render('PlantnetDataBundle:Frontend:collectionList.html.twig', array('collections' => $collections, 'list' => $list, 'current' => 'collections'));

    }
    */

    /**
     * @Route("/collections", name="_collectionList")
     * @Template()
     */
    /*
    public function collectionListAction()
    {
            $dm = $this->get('doctrine.odm.mongodb.document_manager');

            $collections = $dm->getRepository('PlantnetDataBundle:Collection')
                                ->findAll();
            $list = array();
        foreach($collections as $collection){
            $coll = array('collection'=>$collection->getName());

            $module = $dm->getRepository('PlantnetDataBundle:Module')
                                ->findBy(array('collection' => $collection->getId()));
            array_push($coll, $module);
            array_push($list, $coll);
        }


            return $this->render(new ControllerReference('PlantnetDataBundle:Frontend:collectionList.html.twig', array('collections' => $collections, 'list' => $list, 'current' => 'collections')));

    }
    */

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

    /**
     * @Route("/credits", name="_credits")
     * @Template()
     */
    /*
    public function creditsAction()
    {
        return $this->render('PlantnetDataBundle:Default:credits.html.twig', array('current' => 'taxonomy'));
    }
    */

    /**
     * @Route("/mentions", name="_mentions")
     * @Template()
     */
    /*
    public function mentionsAction()
    {
        return $this->render('PlantnetDataBundle:Default:mentions.html.twig', array('current' => 'taxonomy'));
    }
    */

    /**
     * @Route("/contacts", name="_contacts")
     * @Template()
     */
    /*
    public function contactsAction()
    {
        return $this->render('PlantnetDataBundle:Default:contacts.html.twig', array('current' => 'taxonomy'));
    }
    */

}
