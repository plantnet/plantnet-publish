<?php

namespace Plantnet\DataBundle\Controller\Frontend;

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
    /**
     * @Route("/", name="_index")
     * @Template()
     */
    public function indexAction()
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        $collections = $dm->getRepository('PlantnetDataBundle:Collection')
                                ->findAll();
            $modules = array();
        foreach($collections as $collection){
            $coll = array('collection'=>$collection->getName(), 'id'=>$collection->getId());

            $module = $dm->getRepository('PlantnetDataBundle:Module')
                                ->findBy(array('collection.id' => $collection->getId()));
            array_push($coll, $module);

            array_push($modules, $coll);
        }

                    return $this->render('PlantnetDataBundle:Frontend:index.html.twig', array('collections' => $collections, 'list' => $modules, 'current' => 'index'));

    }


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

    /**
     * @Route("/collections", name="_collectionList")
     * @Template()
     */
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

    /**
     * @Route("/collection/{collection}", name="_collection")
     * @Template()
     */
    public function collectionAction($collection)
    {
            $dm = $this->get('doctrine.odm.mongodb.document_manager');

            $coll = $dm->getRepository('PlantnetDataBundle:Collection')
                                ->findOneByName($collection);

            $module = $dm->getRepository('PlantnetDataBundle:Module')
                                ->findBy(array('collection.id' => $coll->getId()));

            $collections = $dm->getRepository('PlantnetDataBundle:Collection')
                                ->findAll();
            $list = array();
        foreach($collections as $collection){
            $collArray = array('collection'=>$collection->getName());

            $mod = $dm->getRepository('PlantnetDataBundle:Module')
                                ->findBy(array('collection' => $collection->getId()));
            array_push($collArray, $module);
            array_push($list, $collArray);
        }

            return $this->render('PlantnetDataBundle:Frontend:collection.html.twig', array('list'=>$list, 'collections' => $collections, 'collection' => $coll, 'module' => $module, 'current' => 'collection'));

    }

    /**
     * @Route("/collection/{collection}/{module}", name="_module")
     * @Template()
     */
    public function moduleAction($collection, $module)
    {
            $dm = $this->get('doctrine.odm.mongodb.document_manager');

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
            $field = $module->getProperties();
            foreach($field as $row){
                if($row->getMain() == true){
                    $display[] = $row->getName();
                }

            }

        switch ($mod->getType())
        {
            case "text":
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->field('module')->references($module)
                ->hydrate(false);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Frontend:datagrid.html.twig', array('paginator' => $paginator, 'field' => $field, 'collection' => $collection, 'module' => $module, 'type' => 'table', 'display' => $display));
                break;
            case "image":
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->field('module')->references($module)
                ->field('images')->exists(true)
                ->hydrate(false);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(20);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Frontend:gallery.html.twig', array('paginator' => $paginator, 'field' => $field, 'collection' => $collection, 'module' => $module, 'type' => 'images', 'display' => $display));
                break;
            case "locality":
                $plantunits=$dm->getRepository('PlantnetDataBundle:Plantunit')
                    ->findBy(array('module.id'=>$module->getId()));
                $locations=array();
                foreach($plantunits as $plantunit)
                {
                    $locs=$plantunit->getLocations();
                    foreach($locs as $point)
                    {
                        $locations[]=$point;
                    }
                }
                return $this->render('PlantnetDataBundle:Frontend:map.html.twig',array(
                    'collection' => $collection,
                    'module' => $module,
                    'type' => 'localisation',
                    'locations' => $locations
                ));
                break;

        }



    }

    /**
     * @Route("/collection/{collection}/{module}/details/{id}", name="_details")
     * @Template()
     */
    public function detailsAction($collection, $module, $id)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $coll = $dm->getRepository('PlantnetDataBundle:Collection')
                                ->findOneByName($collection);
        $modules = $dm->getRepository('PlantnetDataBundle:Module')
                                ->findBy(array('collection' => $coll->getId()));

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

        //$data = $plantunit->getAttributes();


        $data = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->field('module')->references($module)
                ->field('id')->equals($id)
                ->hydrate(false)->getQuery()->execute();

        return $this->render('PlantnetDataBundle:Frontend:details.html.twig', array(

                     'idplantunit' => $plantunit->getId(),
                     'display'  => $display,
                     'data'     => $data,
                     'collection' => $coll,
                     'module'   => $module,
                     'modules'  => $modules));
        
    }

    /**
     * @Route("/taxa", name="_taxa")
     * @Template()
     */
    public function taxonomyAction()
    {
        return $this->render('PlantnetDataBundle:Default:taxonomy.html.twig', array('current' => 'taxonomy'));
    }

    /**
     * @Route("/search", name="_search")
     * @Template()
     */
    public function searchAction()
    {
        return $this->render('PlantnetDataBundle:Default:search.html.twig', array('current' => 'taxonomy'));
    }

    /**
     * @Route("/credits", name="_credits")
     * @Template()
     */
    public function creditsAction()
    {
        return $this->render('PlantnetDataBundle:Default:credits.html.twig', array('current' => 'taxonomy'));
    }

    /**
     * @Route("/mentions", name="_mentions")
     * @Template()
     */
    public function mentionsAction()
    {
        return $this->render('PlantnetDataBundle:Default:mentions.html.twig', array('current' => 'taxonomy'));
    }

    /**
     * @Route("/contacts", name="_contacts")
     * @Template()
     */
    public function contactsAction()
    {
        return $this->render('PlantnetDataBundle:Default:contacts.html.twig', array('current' => 'taxonomy'));
    }

}
