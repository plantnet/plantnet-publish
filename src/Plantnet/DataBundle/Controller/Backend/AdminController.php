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
        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        $user=$this->container->get('security.context')->getToken()->getUser();

        $collections = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findBy(array('user.id'=>$user->getId()));
        $modules = array();
        foreach($collections as $collection){
            $coll = array(
                'collection'=>$collection->getName(),
                'id'=>$collection->getId(),
                'owner'=>$collection->getUser()->getUsernameCanonical()
            );

            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->findBy(array('collection.id' => $collection->getId()));
            array_push($coll, $module);

            array_push($modules, $coll);
        }

        return $this->render('PlantnetDataBundle:Backend:index.html.twig',array('collections' => $collections, 'list' => $modules, 'current' => 'administration'));
    }

    /**
     * @Route("/collection/{collection}", name="admin_collection_view")
     * @Template()
     */
    public function collectionAction($collection)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneByName($collection);

        $modules = $dm->getRepository('PlantnetDataBundle:Module')
            ->findBy(array('collection.id' => $collection->getId()));
        $modules_id=array();
        foreach($modules as $module)
        {
            $modules_id[]=$module->getId();
        }

        $nb_modules=count($modules);

        $nb_plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->field('module.id')->in($modules_id)
            ->getQuery()
            ->execute()
            ->count();

        $nb_images=$dm->createQueryBuilder('PlantnetDataBundle:Image')
            ->field('module.id')->in($modules_id)
            ->getQuery()
            ->execute()
            ->count();

        $nb_locations=$dm->createQueryBuilder('PlantnetDataBundle:Location')
            ->field('module.id')->in($modules_id)
            ->getQuery()
            ->execute()
            ->count();

        return $this->render('PlantnetDataBundle:Backend\Admin:collection_view.html.twig', array(
            'collection'=>$collection,
            'nb_modules'=>$nb_modules,
            'nb_plantunits'=>$nb_plantunits,
            'nb_images'=>$nb_images,
            'nb_locations'=>$nb_locations
        ));
    }

    /**
     * @Route("/collection/{collection}/module/{module}", name="admin_module_view")
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
            case 'text':
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->field('module')->references($module)
                    ->hydrate(false);
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
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->field('module')->references($module)
                    ->field('images')->exists(true)
                    ->hydrate(false);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Backend\Admin:gallery.html.twig', array(
                    'paginator' => $paginator,
                    'field' => $field,
                    'collection' => $collection,
                    'module' => $module,
                    'display' => $display
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
                        'module.$id'=>$mod->getId()
                    ),
                    array('_id'=>1,'latitude'=>1,'longitude'=>1)
                );
                unset($id_plantunits);
                foreach($c_locations as $id=>$l)
                {
                    $loc=array();
                    $loc['id']=$id;
                    $loc['latitude']=$l['latitude'];
                    $loc['longitude']=$l['longitude'];
                    $locations[]=$loc;
                }
                unset($c_locations);
                unset($m);
                $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                return $this->render('PlantnetDataBundle:Backend\Admin:map.html.twig',array(
                    'collection' => $collection,
                    'module' => $module,
                    'locations' => $locations,
                    'layers' => $layers
                ));
                /*
                $localised = $dm->getRepository('PlantnetDataBundle:Plantunit')->findBy(array('modules'=>$module->getId()));
                $location = array();
                foreach($localised as $plantunit){
                    $point = $dm->getRepository('PlantnetBotaBundle:DataMap')
                        ->findLocalisation($plantunit);
                    array_push($location, $point[0]);
                }
                return $this->render('PlantnetDataBundle:Backend\Admin:map.html.twig', array(
                    'collection' => $collection,
                    'module' => $module,
                    'location' => $location
                ));
                */
                break;
        }
    }
    
    /**
     * @Route("/collection/{id}/module/{idmodule}/import_moduledata", name="import_moduledata_to_delete")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Admin:import_moduledata.html.twig")
     */
    public function importmodAction_to_delete($id, $idmodule)//à supprimer ?
    {
        $request = $this->container->get('request');


        if($request->isXmlHttpRequest())
            {

                $dm = $this->get('doctrine.odm.mongodb.document_manager');

                $module = $dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);

                $csvfile = __DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getName_fname().'.csv';
                $handle = fopen($csvfile, "r");
                $columns=fgetcsv($handle,0,";");
                $fields = array();
                $attributes = $module->getProperties();
                foreach($attributes as $field){
                    $fields[] = $field;
                }
                
                //echo "Memory usage before: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
                $s = microtime(true);

                $batchSize = 1000;
                $rowCount = '';
               while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                    $num = count($data);



                    /*$mod = $dm->getRepository('PlantnetDataBundle:Module')
                                      ->findOneBy(array('name' => $module->getName(), 'collection.id' => $collection->getId()));

                   if($mod->getParent()){
                         $moduleParent = $dm->createQueryBuilder('PlantnetDataBundle:Module')
                                      ->field('parent.id')->equals($mod->getParent()->getId());

                         $idmodule = $dm->getRepository('PlantnetDataBundle:Property')
                                      ->findOneBy(array('modules' => $moduleParent->getId(), 'type' => 'idmodule'));

                         for ($c=0; $c < $num; $c++) {
                             $datafield = $dm->getRepository('PlantnetBotaBundle:Properties')->findOneBy(array('attribute'=>$field[$c], 'modules'=>$mod->getId()));

                             if ($datafield->getType() == "module_parent"){
                                    $value = $data[$c];
                             }
                         }

                         $idparent = $dm->getRepository('PlantnetViewBundle:DataText')
                                    ->findParent($idmodule->getId(), $idmodule->getAttribute(), $value);

                         if($idparent){
                                    $plantunit->setParent($idparent[0]->getPlantunit());
                          }

                   }*/

                    $rowCount++;


                    if ($module->getType() == 'text'){
                        $plantunit = new Plantunit();
                        $plantunit->setModule($module);
                        $attributes = array();
                        for ($c=0; $c < $num; $c++) {
                            //$attributes[utf8_encode($field[$c])] = utf8_encode($data[$c]) ;

                            
                            $value = $this->data_encode($data[$c]);
                                    $attributes[$fields[$c]->getName()] = $value;
                            switch($fields[$c]->getType()){
                                case "idmodule":
                                    $plantunit->setIdentifier($value);
                                break;
                                case "idparent":
                                    $plantunit->setIdparent($value);
                                    break;
                            }
                            /**
                             * Test1
                             */
                            /*$data_encoding = mb_detect_encoding($data[$c]) ;
                            if($data_encoding == "UTF-8" && mb_check_encoding($data[$c],"UTF-8")){
                                $attributes[$fields[$c]->getName()] = array('value' => ($data[$c]),
                                                                            'main' => $fields[$c]->getMain(),
                                                                            'details' => $fields[$c]->getDetails());
                            }
                            else {
                                //$attributes[$fields[$c]->getName()] = utf8_encode($data[$c]) ;
                                $attributes[$fields[$c]->getName()] = array('value' => utf8_encode($data[$c]),
                                                                            'main' => $fields[$c]->getMain(),
                                                                            'details' => $fields[$c]->getDetails());
                            }*/

                            /**
                             * Test2
                             */
                            /*$value = new Data();
                            $data_encoding = mb_detect_encoding($data[$c]) ;
                            if($data_encoding == "UTF-8" && mb_check_encoding($data[$c],"UTF-8")){
                                $value->setAttribute($fields[$c]);
                                $value->setValue($data[$c]);
                            }
                            else {
                                $value->setAttribute($fields[$c]);
                                $value->setValue(utf8_encode($data[$c]));
                            }
                            if($fields[$c]->getType()){
                                $value->setType($fields[$c]->getType());
                            }
                            
                            $plantunit->addDatas($value);*/

                            /**
                             * Test3
                             */
                            /*$data_encoding = mb_detect_encoding($data[$c]) ;
                            if($data_encoding == "UTF-8" && mb_check_encoding($data[$c],"UTF-8")){
                                $plantunit->addAttribute($fields[$c]->getName(), $data[$c], $fields[$c]->getMain(), $fields[$c]->getDetails());

                            }
                            else {
                                $plantunit->addAttribute($fields[$c]->getName(), utf8_encode($data[$c]), $fields[$c]->getMain(), $fields[$c]->getDetails());

                            }*/
                        }

                            $plantunit->setAttributes($attributes);

                            if($module->getParent()){
                                    $dm->persist($plantunit);
                                    $moduleid = $module->getParent()->getId();
                                    $parent = $dm->getRepository('PlantnetDataBundle:Plantunit')
                                        ->findOneBy(array('module.id' => $moduleid, 'identifier' => $plantunit->getIdparent()));

                                    $plantunit->setParent($parent);
                                }
                            

                    }elseif ($module->getType() == 'image'){
                        $image = new Image();
                        
                        $attributes = array();
                        for ($c=0; $c < $num; $c++) {


                            $data_encoding = mb_detect_encoding($data[$c]) ;
                            if($data_encoding == "UTF-8" && mb_check_encoding($data[$c],"UTF-8")){
                                $attributes[$fields[$c]->getName()] = $data[$c];
                            }else {
                                $attributes[$fields[$c]->getName()] = utf8_encode($data[$c]);
                                }

                            
                            switch($fields[$c]->getType()){
                                case "file":
                                    $image->setPath($data[$c]);
                                    break;
                                case "copyright":
                                    $image->setCopyright($data[$c]);
                                    break;
                                case "idparent":
                                    $image->setIdparent($data[$c]);
                                    break;

                            }

                        }

                        $image->setProperty($attributes);
                        $dm->persist($image);
                        $moduleid = $module->getParent()->getId();
                        $plantunit = $dm->getRepository('PlantnetDataBundle:Plantunit')
                            ->findOneBy(array('module.id' => $moduleid, 'identifier' => $image->getIdparent()));
                        
                        $plantunit->addImages($image);

                        //$dm->persist($plantunit);
                        
                    }elseif ($module->getType() == 'locality'){
                        $localisation = new DataMap();
                            for ($c=0; $c < $num; $c++) {
                                $datafield = $dm->getRepository('PlantnetBotaBundle:Properties')->findOneBy(array('attribute'=>utf8_encode($field[$c]), 'modules'=>$mod->getId()));
                                if ($datafield->getType() == "lon"){
                                    $localisation->setLongitude($data[$c]);
                                    $localisation->setProperties($datafield);
                                }elseif($datafield->getType() == "lat"){
                                    $localisation->setLatitude($data[$c]);
                                    $localisation->setProperties($datafield);
                                }elseif($datafield->getType() == "loc"){
                                    $localisation->setLocality(utf8_encode($data[$c]));
                                    $localisation->setProperties($datafield);
                                }
                            }

                        //$localisation->setPlantunit($plantunit);
                        $dm->persist($localisation);
                        //$dm->persist($plantunit);
                    }


                    $dm->persist($plantunit);

                    if (($rowCount % $batchSize) == 0) {
                        $dm->flush();
                        //$dm->detach($plantunit);
                        //unset($plantunit);
                        //gc_collect_cycles();

                    }
        }
        $dm->flush();

        //echo "Memory usage after: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
        $e = microtime(true);
        echo ' Inserted '.$rowCount.' objects in ' . ($e - $s) . ' seconds' . PHP_EOL;
                
        fclose($handle);

        /*$usermail = $this->get('security.context')->getToken()->getUser()->getEmail();

        // Récupération du mailer service.
        $mailer = $this->get('mailer');

        // Création de l'e-mail : le service mailer utilise SwiftMailer, donc nous créons une instance de Swift_Message.
        $message = \Swift_Message::newInstance()
            ->setSubject('Importation success')
            ->setFrom('support@plantnet-project.org')
            ->setTo($usermail)
            ->setBody('Your data for the ' .$module->getName() .'module was imported');

        // Retour au service mailer, nous utilisons sa méthode « send() » pour envoyer notre $message.
        $mailer->send($message);*/

        return $this->container->get('templating')->renderResponse('PlantnetDataBundle:Backend\Admin:import_moduledata.html.twig', array(
               'importCount' => 'Importation Success: '.$rowCount.' objects imported'

        ));


            }else{
            return $this->import_dataAction($id, $idmodule);
        }

    }

}
