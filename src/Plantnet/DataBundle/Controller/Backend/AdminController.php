<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Symfony\Component\HttpFoundation\Response;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;

//?
use Plantnet\DataBundle\Document\Plantunit;
use Plantnet\DataBundle\Document\Image;
use Plantnet\DataBundle\Document\Location;

/**
 * Admin controller.
 *
 * @Route("/admin")
 */
class AdminController extends Controller
{
    /**
     * @Route("/", name="admin_index")
     * @Template("PlantnetDataBundle:Backend:index.html.twig")
     */
    public function indexAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $collections = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findBy(array(
                'user.id'=>$user->getId()
            ));
        return $this->render('PlantnetDataBundle:Backend:index.html.twig',array(
            'collections' => $collections,
            'current' => 'administration'
        ));
    }

    /**
     * @Route("/collection/{collection}", name="admin_collection_view")
     * @Template()
     */
    public function collectionAction($collection)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'name'=>$collection,
                'user.id'=>$user->getId()
            ));
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        return $this->render('PlantnetDataBundle:Backend\Admin:collection_view.html.twig', array(
            'collection'=>$collection
        ));
    }

    /**
     * @Route("/collection/{collection}/module/{module}", name="admin_module_view")
     * @Template()
     */
    public function moduleAction($collection, $module)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'name'=>$collection,
                'user.id'=>$user->getId()
            ));
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $mod = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=> $module,
                'collection.id' => $collection->getId()
            ));
        if (!$mod) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
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
            case 'text':
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->field('module')->references($module)
                    ->hydrate(true);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Backend\Admin:datagrid.html.twig', array(
                    'paginator' => $paginator,
                    'field' => $field,
                    'collection' => $collection,
                    'module' => $module,
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
                return $this->render('PlantnetDataBundle:Backend\Admin:gallery.html.twig', array(
                    'paginator' => $paginator,
                    'collection' => $collection,
                    'module' => $mod
                ));
                break;
            case 'locality':
                $db=$this->container->getParameter('mdb_base');
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
                return $this->render('PlantnetDataBundle:Backend\Admin:map.html.twig',array(
                    'collection' => $collection,
                    'module' => $mod,
                    'locations' => $locations,
                    'layers' => $layers
                ));
                break;
        }
    }
}
