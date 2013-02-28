<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Plantnet\DataBundle\Document\Plantunit;
use Plantnet\DataBundle\Document\Collection;
use Plantnet\DataBundle\Document\Module;
use Plantnet\DataBundle\Document\Property;
use Plantnet\DataBundle\Document\Image;
use Plantnet\DataBundle\Document\Location;
use Symfony\Component\HttpFoundation\Response;

use
    Plantnet\DataBundle\Form\ImportFormType,
    Plantnet\DataBundle\Form\ModuleFormType,
    Plantnet\DataBundle\Form\Type\CollectionType;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;

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
            $coll = array('collection'=>$collection->getName(), 'id'=>$collection->getId());

            $module = $dm->getRepository('PlantnetDataBundle:Module')
                                ->findBy(array('collection.id' => $collection->getId()));
            array_push($coll, $module);

            array_push($modules, $coll);
        }



        return $this->render('PlantnetDataBundle:Backend:index.html.twig',array('collections' => $collections, 'list' => $modules, 'current' => 'administration'));
    }


    /**
     * Displays a form to create a new Collection entity.
     *
     * @Route("/collection/new", name="collection_new")
     * @Template()
     */
    public function new_collectionAction()
    {
        $document = new Collection();
        $form   = $this->createForm(new CollectionType(), $document);
        return array(
            'entity' => $document,
            'form'   => $form->createView()
        );
    }

    /**
     * Creates a new Collection entity.
     *
     * @Route("/collection/create", name="collection_create")
     * @Method("post")
     * @Template()
     */
    public function createAction()
    {
        $document  = new Collection();
        $request = $this->getRequest();
        $form    = $this->createForm(new CollectionType(), $document);

        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);

            if ($form->isValid()) {
                $dm = $this->get('doctrine.odm.mongodb.document_manager');
                $user=$this->container->get('security.context')->getToken()->getUser();
                $document->setUser($user);
                $dm->persist($document);
                $dm->flush();

                return $this->redirect($this->generateUrl('module_new', array('id' => $document->getId())));

            }
        }

        return array(
            'entity' => $document,
            'form'   => $form->createView()
        );


    }

    /**
     * @Route("/collection/{id}/new_module", name="module_new")
     * @Template()
     */
    public function new_moduleAction($id)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        $collection = $dm->getRepository('PlantnetDataBundle:Collection')->find($id);
        if (!$collection) {
                    throw $this->createNotFoundException('Unable to find Collection entity.');
                }

        $module = new Module();

        $module->setCollection($collection);
        $collection->addModules($module);

        $parents_module = $dm->getRepository('PlantnetDataBundle:Module')
                             ->findBy(array('collection.id' => $collection->getId()));
        
        $idparent = array();
        foreach($parents_module as $mod){
            $idparent[$mod->getId()] = $mod->getName();
        }
        
        $form = $this->createForm(new ModuleFormType(), $module, array(
                            'idparent' => $idparent));
        
        //$form    = $this->createForm(new ModuleFormType(), $module);

        return array(
            'idparent'=>$idparent,
            'module' => $module,
            'collection' => $collection,
            'form' => $form->createView(),
        );
    }

    /**
     * @Route("/collection/{id}/create_module", name="module_create")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Admin:new_module.html.twig")
     */
    public function create_moduleAction($id)
    {
        $module  = new Module();

        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')->find($id);

        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }

        $request = $this->getRequest();

        $collection->addModules($module);
        $parents_module = $dm->getRepository('PlantnetDataBundle:Module')
                             ->findBy(array('collection.id' => $collection->getId()));

        $idparent = array();
        foreach($parents_module as $mod){
            $idparent[$mod->getId()] = $mod->getName();
        }
        
        $form    = $this->createForm(new ModuleFormType(), $module, array(
                            'idparent' => $idparent));


        if ('POST' === $request->getMethod()) {

            $form->bindRequest($request);

            if ($form->isValid()) {

                $dm = $this->get('doctrine.odm.mongodb.document_manager');
                 $module->setCollection($collection);
                $idparent = $request->request->get('modules');
                
                if(array_key_exists('parent', $idparent) && $idparent['parent'] != null){
                    $module_parent = $dm->getRepository('PlantnetDataBundle:Module')->find($idparent['parent']);
                    $module->setParent($module_parent);
                }

                $module->setType($module->getType());

                $uploadedFile = $module->getFile();
                try{
                    $uploadedFile->move(
    	                    __DIR__."/../../Resources/uploads/".$module->getCollection()."/",
    	                    $module->getName().'.csv'
    	                );
                }
                catch(FilePermissionException $e)
                        {
                            return false;
                        }
                catch(\Exception $e)
                        {
                            throw new \Exception($e->getMessage());
                        }

                $csv = __DIR__."/../../Resources/uploads/".$module->getCollection()."/".$module->getName().'.csv';
                
                $handle = fopen($csv, "r");
                $field=fgetcsv($handle,0,";");

                foreach($field as $col){
                    $property = new Property();
                    $cur_encoding = mb_detect_encoding($col) ;
                    if($cur_encoding == "UTF-8" && mb_check_encoding($col,"UTF-8")){
                        $property->setName($col);
                    }
                    else {
                        $property->setName(utf8_encode($col));

                    }


                    $property->setDetails(true);
                    $dm->persist($property);
                    $module->addProperties($property);
                }

                $dm->persist($module);
                $dm->flush();

                return $this->redirect($this->generateUrl('fields_type', array('id' => $collection->getId(), 'idmodule' => $module->getId())));

            }
        }
        return array(
            'collection' => $collection,
            'module' => $module,
            'form'   => $form->createView()
        );
    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/fields_selection", name="fields_type")
     * @Template()
     */
    public function fields_typeAction($id, $idmodule)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        $module = $dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')->find($id);


        if (!$module) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }

        $form    = $this->get('form.factory')->create(new ImportFormType(), $module);
        
$count='';
        return array(
            'collection' => $collection,
            'module'      => $module,
            'importCount' => $count,
            'form'   => $form->createView(),
        );

    }


    /**
     * @Route("/collection/{id}/module/{idmodule}/save_fields", name="save_fields")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Admin:fields_type.html.twig")
     */
    public function save_fieldsAction($id, $idmodule)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        $module = $dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);

        if (!$module) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }


        $form = $this->createForm(new ImportFormType(), $module);
        

        $request = $this->getRequest();

        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);

            if ($form->isValid()) {
                $dm = $this->get('doctrine.odm.mongodb.document_manager');

                $dm->persist($module);
                $dm->flush();

                return $this->redirect($this->generateUrl('import_data', array('id' => $id, 'idmodule' => $idmodule)));
            }
        }

        return array(

            'module'      => $module,
            'form'   => $form->createView(),
        );

    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/import_data", name="import_data")
     * @Template()
     */
    public function import_dataAction($id, $idmodule)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        $module = $dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')->find($id);
        
        if (!$module) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }

        $form    = $this->get('form.factory')->create(new ImportFormType(), $module);
        $count='';
        return array(
            'collection'  => $collection,
            'module'      => $module,
            'importCount' => $count,
            'form'   => $form->createView(),
        );

    }

    


    /**
     * @Route("/collection/{id}/module/{idmodule}/importation", name="importation")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Admin:import_moduledata.html.twig")
     */
    public function importationAction($id, $idmodule)
    {
        $request = $this->container->get('request');

        set_time_limit(0);
        if($request->isXmlHttpRequest())
            {

                $dm = $this->get('doctrine.odm.mongodb.document_manager');
                $configuration = $dm->getConnection()->getConfiguration();
                $configuration->setLoggerCallable(null);

                $module = $dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);

                /*
                 * Open the uploaded csv
                 */
                $csvfile = __DIR__."/../../Resources/uploads/".$module->getCollection()."/".$module->getName().'.csv';
                $handle = fopen($csvfile, "r");

                /*
                 * Get the module properties
                 */
                $columns=fgetcsv($handle,0,";");
                $fields = array();
                $attributes = $module->getProperties();
                foreach($attributes as $field){
                    $fields[] = $field;
                }


                /*
                 * Initialise the metrics
                 */
                //echo "Memory usage before: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
                $s = microtime(true);

                $batchSize = 500;
                $rowCount = '';


                    while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                        $num = count($data);
                        $rowCount++;
                        if ($module->getType() == 'text'){

                                $plantunit = new Plantunit();
                                $plantunit->setModule($module);

                                $attributes = array();
                                for ($c=0; $c < $num; $c++) {
                                    $value = $this->data_encode($data[$c]);
                                    $attributes[$fields[$c]->getName()] = $value;
                                    switch($fields[$c]->getType()){
                                        case 'idmodule':
                                            $plantunit->setIdentifier($value);
                                            break;
                                        case 'idparent':
                                            $plantunit->setIdparent($value);
                                            break;
                                    }
                                }
                                    
                                $plantunit->setAttributes($attributes);
                                $dm->persist($plantunit);
                                if($module->getParent()){

                                    
                                    $moduleid = $module->getParent()->getId();
                                    $parent = $dm->getRepository('PlantnetDataBundle:Plantunit')
                                        ->findOneBy(array('module.id' => $moduleid, 'identifier' => $plantunit->getIdparent()));

                                    $plantunit->setParent($parent);
                                    $dm->persist($plantunit);
                                }

                        }elseif ($module->getType() == 'image'){
                                $image = new Image();

                                $attributes = array();
                                for ($c=0; $c < $num; $c++) {
                                    $value = $this->data_encode($data[$c]);
                                    $attributes[$fields[$c]->getName()] = $value;
                                    switch($fields[$c]->getType()){
                                        case 'file':
                                            $image->setPath($value);
                                            break;
                                        case 'copyright':
                                            $image->setCopyright($value);
                                            break;
                                        case 'idparent':
                                            $image->setIdparent($value);
                                            break;
                                        case 'idmodule':
                                            $image->setIdentifier($value);
                                            break;
                                    }
                                }
                                $image->setProperty($attributes);


                                if($module->getParent()){

                                    $dm->persist($image);
                                    $moduleid = $module->getParent()->getId();
                                    $parent = $dm->getRepository('PlantnetDataBundle:Plantunit')
                                        ->findOneBy(array('module.id' => $moduleid, 'identifier' => $image->getIdparent()));
                                   if($parent){
                                       $parent->addImages($image);
                                   }


                                }else{
                                    $plantunit = new Plantunit();
                                    $plantunit->setModule($module);
                                    $plantunit->setAttributes($attributes);
                                    $plantunit->setIdentifier($image->getIdentifier());
                                    $plantunit->addImages($image);
                                    $dm->persist($plantunit);
                                }
                            }elseif ($module->getType() == 'locality'){
                                $location = new Location();
                                $attributes = array();
                                for($c=0; $c < $num; $c++)
                                {
                                    $value = $this->data_encode($data[$c]);
                                    $attributes[$fields[$c]->getName()] = $value;
                                    switch($fields[$c]->getType()){
                                        case 'lon':
                                            $location->setLongitude(str_replace(',','.',$value));
                                            break;
                                        case 'lat':
                                            $location->setLatitude(str_replace(',','.',$value));
                                            break;
                                        case 'idparent':
                                            $location->setIdparent($value);
                                            break;
                                        case 'idmodule':
                                            $location->setIdentifier($value);
                                            break;
                                    }
                                }
                                $location->setProperty($attributes);
                                $parent=null;
                                if($module->getParent())
                                {
                                    $parent_q=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                                        ->field('module.id')->equals($module->getParent()->getId())
                                        ->field('identifier')->equals($location->getIdparent())
                                        ->getQuery()
                                        ->execute();
                                        foreach($parent_q as $p)
                                        {
                                            $parent=$p;
                                        }
                                }
                                if($parent)
                                {
                                    $parent->addLocations($location);
                                    $dm->persist($parent);
                                    $location->setPlantunit($parent);
                                    $dm->persist($location);
                                }
                                else
                                {
                                    $plantunit=new Plantunit();
                                    $plantunit->setModule($module);
                                    $plantunit->setAttributes($attributes);
                                    $plantunit->setIdentifier($location->getIdentifier());
                                    $plantunit->addLocations($location);
                                    $dm->persist($plantunit);
                                    $location->setPlantunit($plantunit);
                                    $dm->persist($location);
                                }
                            }
                            
                            if (($rowCount % $batchSize) == 0) {
                                $dm->flush();
                                $dm->clear();
                                $module = $dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                                //$dm->detach($plantunit);
                                //unset($plantunit);
                                //gc_collect_cycles();
                            }
                        }

        $dm->flush();
        $dm->clear();

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

    protected function data_encode($data){
        $data_encoding = mb_detect_encoding($data) ;
            if($data_encoding == "UTF-8" && mb_check_encoding($data,"UTF-8")){
                   $format = $data;
            }else {
                  $format = utf8_encode($data);
            }

        return $format;
    }

    /**
     * @Route("/{collection}/{module}", name="admin_module_view")
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
                return $this->render('PlantnetDataBundle:Backend\Admin:datagrid.html.twig', array('paginator' => $paginator, 'field' => $field, 'collection' => $collection, 'module' => $module, 'display' => $display));
                break;
            case "image":
                $queryBuilder = $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->field('module')->references($module)
                ->field('images')->exists(true)
                ->hydrate(false);
                $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page', 1));
                return $this->render('PlantnetDataBundle:Backend\Admin:gallery.html.twig', array('paginator' => $paginator, 'field' => $field, 'collection' => $collection, 'module' => $module, 'display' => $display));
                break;
            case "locality":
            $localised = $dm->getRepository('PlantnetDataBundle:Plantunit')->findBy(array('modules'=>$module->getId()));

                $location = array();
                foreach($localised as $plantunit){
                    $point = $dm->getRepository('PlantnetBotaBundle:DataMap')
                                ->findLocalisation($plantunit);
                    array_push($location, $point[0]);

                }

                return $this->render('PlantnetDataBundle:Backend\Admin:map.html.twig', array('collection' => $collection, 'module' => $module, 'location' => $location));
                break;

        }



    }
    
    /**
     * @Route("/collection/{id}/module/{idmodule}/import_moduledata", name="import_moduledata")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Admin:import_moduledata.html.twig")
     */
    public function importmodAction($id, $idmodule)
    {
        $request = $this->container->get('request');


        if($request->isXmlHttpRequest())
            {

                $dm = $this->get('doctrine.odm.mongodb.document_manager');

                $module = $dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);

                $csvfile = __DIR__."/../../Resources/uploads/".$module->getCollection()."/".$module->getName().'.csv';
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
