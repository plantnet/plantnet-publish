<?php

namespace Plantnet\DataBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Plantnet\DataBundle\Document\Plantunit;
use Plantnet\DataBundle\Document\Collection;
use Plantnet\DataBundle\Document\Module;
use Plantnet\DataBundle\Document\Property;
use Plantnet\DataBundle\Document\File;
use Plantnet\DataBundle\Document\Image;
use Symfony\Component\HttpFoundation\Response;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;

class DefaultController extends Controller
{
    /**
     * @Route("/mongo")
     * @Template()
     */
    /*
    public function indexAction()
    {
        $product = new Plantunit();
        $product->setAttribute(array('test'=>'attribute'));
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
            $dm->persist($product);
            $dm->flush();
        return new Response('Created product id '.$product->getId());
    }
    */

    /**
     * @Route("/mongo_import")
     * @Template()
     */
    /*
    public function mongo_importAction()
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        $csvfile = __DIR__."/../Resources/uploads/ifp.csv";
        $handle = fopen($csvfile, "r");
        $field=fgetcsv($handle,0,";");

        
        echo "Memory usage before: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
        $s = microtime(true);
        $num=null;

        $collection = new Collection();
        $collection->setName('Ifp');
        $dm->persist($collection);


        $module = new Module();
        $module->setName('species');
        
        foreach($field as $col){
            $property = new Property();
            $property->setName($col);
            $property->setDetails(true);
            $dm->persist($property);
            $module->addProperties($property);
        }
        
        $module->setCollection($collection);
        
        $collection->addModules($module);

        $dm->persist($module);
        $dm->flush();

        $batchSize = 1000;
        $rowCount = 0;
        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            $num = count($data);
            $plantunit = new Plantunit();

            
            $attributes = array();
            for ($c=0; $c < $num; $c++) {
                $attributes[utf8_encode($field[$c])] = utf8_encode($data[$c]) ;
            }
                $rowCount++;
                $plantunit->setAttribute($attributes);
                $plantunit->setModule($module);
                $dm->persist($plantunit);


            
            if (($rowCount % $batchSize) == 0) {

                        $dm->flush();
                        //$dm->clear();
                        //$dm->detach($plantunit);

            }


            //$module->addPlantunits($plantunit);
        }
        //$dm->persist($module);
        $dm->flush();
        
        echo "Memory usage after: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;

        $e = microtime(true);
        echo ' Inserted '.$rowCount.' objects in ' . ($e - $s) . ' seconds' . PHP_EOL;

        // $file = new SplFileObject(__DIR__."/../Resources/uploads/species_full.csv");
        // $file->setFlags(SplFileObject::READ_CSV);
        // $file->setCsvControl(';');
            
        // foreach($file as $line)
        //     {
        //         $plantunit = new Plantunit();

        //     }

        return new Response('Plantunit imported');
    }
    */

    /**
     * @Route("/mongo_show/{collection}/{module}")
     * @Template()
     */
    /*
    public function mongo_showAction($collection, $module)
    {
        // $dm = $this->get('doctrine.odm.mongodb.document_manager');
        // $coll = $dm->getRepository('PlantnetAdminBundle:Collection')
        //                         ->findOneByName($collection);
        
        // $modules = 'vide';

        // $module = $dm->getRepository('PlantnetAdminBundle:Module')
        //                         ->findOneBy(array('name' => $module, 'collection' => $coll->getId()));

        

        $collection = $dm->getRepository('PlantnetAdminBundle:Collection')
                                ->findOneByName($collection);

        $module = $dm->getRepository('PlantnetAdminBundle:Module')
                                ->findOneBy(array('name'=> $module, 'collection.id' => $collection->getId()));
        
        $module = $dm->getRepository('PlantnetAdminBundle:Module')
                    ->find($module->getId());
        
        
        $queryBuilder = $dm->createQueryBuilder('PlantnetAdminBundle:Plantunit')
                ->field('module')->references($module);
        
        $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
        $paginator->setMaxPerPage(50);
        $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
        return $this->render('PlantnetAdminBundle::datagrid.html.twig', array('paginator' => $paginator, 'module'=>$module));

    }
    */

    /**
     * @Route("/mongo_image")
     * @Template()
     */
    /*
    public function mongo_imageAction()
    {

            $dm = $this->get('doctrine.odm.mongodb.document_manager');

            $plantunit = new Plantunit();
            $plantunit = $dm->getRepository('PlantnetDataBundle:Plantunit')
                                        ->find('4e6b9037d206a8f53a080000');

            $attributes =  array();
            $attributes['test'] = 'newvalue';

            $plantunit->setAttributes($attributes);

            $image = new Image();
            $image->setPath('newPath');
            

            $plantunit->addImages($image);

            $dm->persist($plantunit);
            $dm->flush();

    }
    */

    /*
    public function collectionListAction()
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

        return $this->render('PlantnetDataBundle:Backend\Collection:collectionlist.html.twig',array('collections' => $collections, 'list' => $modules, 'current' => 'administration'));
    }
    */

    /*
    public function menuCollectionListAction()
    {
            $dm = $this->get('doctrine.odm.mongodb.document_manager');

            $collections = $dm->getRepository('PlantnetDataBundle:Collection')
                                ->findAll();
            $list = array();
        foreach($collections as $collection){
            $coll = array(
                'collection'=>$collection->getName()
            );

            $module = $dm->getRepository('PlantnetDataBundle:Module')
                                ->findBy(array('collection' => $collection->getId()));
            array_push($coll, $module);
            array_push($list, $coll);
        }


            return $this->render('PlantnetDataBundle:Frontend:menuCollectionList.html.twig', array('collections' => $collections, 'list' => $list, 'current' => 'collections'));

    }
    */
}
