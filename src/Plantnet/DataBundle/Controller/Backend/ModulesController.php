<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Plantnet\DataBundle\Document\Module,
    Plantnet\DataBundle\Document\Plantunit,
    Plantnet\DataBundle\Document\Property,
    Plantnet\DataBundle\Document\Image,
    Plantnet\DataBundle\Document\Location;

use Plantnet\DataBundle\Form\ImportFormType,
    Plantnet\DataBundle\Form\Type\ModulesType,
    Plantnet\DataBundle\Form\ModuleFormType;

/**
 * Module controller.
 *
 * @Route("/admin/module")
 */
class ModulesController extends Controller
{
    /**
     * Displays a form to create a new Module entity.
     *
     * @Route("/collection/{id}/module/new", name="module_new")
     * @Template()
     */
    public function module_newAction($id)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->find($id);
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = new Module();
        $parents_module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findBy(array('collection.id' => $collection->getId()));
        $idparent = array();
        foreach($parents_module as $mod){
            $idparent[$mod->getId()] = $mod->getName();
        }
        $form = $this->createForm(new ModuleFormType(), $module, array('idparent' => $idparent));
        return array(
            'idparent' => $idparent,
            'module' => $module,
            'collection' => $collection,
            'form' => $form->createView(),
        );
    }

    /**
     * Creates a new Module entity.
     *
     * @Route("/collection/{id}/module/create", name="module_create")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Modules:module_new.html.twig")
     */
    public function module_createAction($id)
    {
        $module = new Module();
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->find($id);
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $request = $this->getRequest();
        // $collection->addModules($module);
        $parents_module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findBy(array('collection.id' => $collection->getId()));
        $idparent = array();
        foreach($parents_module as $mod){
            $idparent[$mod->getId()] = $mod->getName();
        }
        $form = $this->createForm(new ModuleFormType(), $module, array('idparent' => $idparent));
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
                        __DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/',
                        $module->getName_fname().'.csv'
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
                $csv = __DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getName_fname().'.csv';
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
                if($module->getType()=='image')
                {
                    $module->setUploaddir($collection->getAlias().'_'.$module->getName_fname());
                }
                $dm->persist($module);
                $dm->flush();
                return $this->redirect($this->generateUrl('fields_type', array('id' => $collection->getId(), 'idmodule' => $module->getId())));
            }
        }
        return array(
            'collection' => $collection,
            'module' => $module,
            'form' => $form->createView()
        );
    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/fields_selection", name="fields_type")
     * @Template()
     */
    public function fields_typeAction($id, $idmodule)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->find($idmodule);
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->find($id);
        if (!$module) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $form = $this->get('form.factory')->create(new ImportFormType(), $module);
        $count='';
        return array(
            'collection' => $collection,
            'module' => $module,
            'importCount' => $count,
            'form' => $form->createView(),
        );
    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/save_fields", name="save_fields")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Modules:fields_type.html.twig")
     */
    public function save_fieldsAction($id, $idmodule)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->find($idmodule);
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
            'module' => $module,
            'form' => $form->createView(),
        );
    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/import_data", name="import_data")
     * @Template()
     */
    public function import_dataAction($id, $idmodule)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->find($idmodule);
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->find($id);
        if (!$module) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $form = $this->get('form.factory')->create(new ImportFormType(), $module);
        $count='';
        return array(
            'collection' => $collection,
            'module' => $module,
            'importCount' => $count,
            'form' => $form->createView(),
        );
    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/importation", name="importation")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Modules:import_moduledata.html.twig")
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
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($idmodule);
            if (!$module) {
                throw $this->createNotFoundException('Unable to find Module entity.');
            }
            /*
             * Open the uploaded csv
             */
            $csvfile = __DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getName_fname().'.csv';
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
                    // if($module->getParent()){
                    //     $moduleid = $module->getParent()->getId();
                    //     $parent = $dm->getRepository('PlantnetDataBundle:Plantunit')
                    //         ->findOneBy(array('module.id' => $moduleid, 'identifier' => $plantunit->getIdparent()));
                    //     $plantunit->setParent($parent);
                    //     $dm->persist($plantunit);
                    // }
                }elseif ($module->getType() == 'image'){
                    $image = new Image();
                    $attributes = array();
                    for($c=0; $c < $num; $c++)
                    {
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
                    $image->setModule($module);
                    $parent=null;
                    if($module->getParent())
                    {
                        $parent_q=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->field('module.id')->equals($module->getParent()->getId())
                            ->field('identifier')->equals($image->getIdparent())
                            ->getQuery()
                            ->execute();
                        foreach($parent_q as $p)
                        {
                            $parent=$p;
                        }
                    }
                    if($parent)
                    {
                        // $parent->addImages($image);
                        // $dm->persist($parent);
                        $image->setPlantunit($parent);
                        $dm->persist($image);
                    }
                    else
                    {
                        $plantunit=new Plantunit();
                        $plantunit->setModule($module);
                        $plantunit->setAttributes($attributes);
                        $plantunit->setIdentifier($image->getIdentifier());
                        // $plantunit->addImages($image);
                        $dm->persist($plantunit);
                        $image->setPlantunit($plantunit);
                        $dm->persist($image);
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
                    $location->setModule($module);
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
                        // $parent->addLocations($location);
                        // $dm->persist($parent);
                        $location->setPlantunit($parent);
                        $dm->persist($location);
                    }
                    else
                    {
                        $plantunit=new Plantunit();
                        $plantunit->setModule($module);
                        $plantunit->setAttributes($attributes);
                        $plantunit->setIdentifier($location->getIdentifier());
                        // $plantunit->addLocations($location);
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
            if(file_exists($csvfile))
            {
                unlink($csvfile);
            }
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
            return $this->container->get('templating')->renderResponse('PlantnetDataBundle:Backend\Modules:import_moduledata.html.twig', array(
                'importCount' => 'Importation Success: '.$rowCount.' objects imported'
            ));

        }else{
            return $this->import_dataAction($id, $idmodule);
        }
    }

    protected function data_encode($data)
    {
        $data_encoding = mb_detect_encoding($data) ;
        if($data_encoding == "UTF-8" && mb_check_encoding($data,"UTF-8")){
            $format = $data;
        }else {
            $format = utf8_encode($data);
        }
        return $format;
    }

    /**
     * Displays a form to edit an existing Module entity.
     *
     * @Route("/{id}/edit", name="module_edit")
     * @Template()
     */
    public function module_editAction($id)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $entity = $dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $editForm = $this->get('form.factory')->create(new ModulesType(), $entity);
        $deleteForm = $this->createDeleteForm($id);
        return array(
            'entity' => $entity,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Edits an existing Module entity.
     *
     * @Route("/{id}/update", name="module_update")
     * @Method("post")
     * @Template("PlantnetBotaBundle:Backend\Modules:module_edit.html.twig")
     */
    public function module_updateAction($id)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $entity = $dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $editForm = $this->createForm(new ModulesType(), $entity);
        $deleteForm = $this->createDeleteForm($id);
        $request = $this->getRequest();
        if ('POST' === $request->getMethod()) {
            $editForm->bindRequest($request);
            if ($editForm->isValid()) {
                $dm = $this->get('doctrine.odm.mongodb.document_manager');
                $dm->persist($entity);
                $dm->flush();
                return $this->redirect($this->generateUrl('module_edit', array('id' => $id)));
            }
        }
        return array(
            'entity' => $entity,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Deletes a Module entity.
     *
     * @Route("/{id}/delete", name="module_delete")
     * @Method("post")
     */
    public function module_deleteAction($id)
    {
        $form = $this->createDeleteForm($id);
        $request = $this->getRequest();
        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);
            if ($form->isValid()) {
                $dm = $this->get('doctrine.odm.mongodb.document_manager');
                $module = $dm->getRepository('PlantnetDataBundle:Module')->find($id);
                if(!$module){
                    throw $this->createNotFoundException('Unable to find Module entity.');
                }
                $collection=$module->getCollection();
                if(!$collection){
                    throw $this->createNotFoundException('Unable to find Collection entity.');
                }
                /*
                * Remove children
                */
                $children=$module->getChildren();
                if(count($children))
                {
                    foreach($children as $child)
                    {
                        $this->forward('PlantnetDataBundle:Backend\Modules:module_delete',array(
                            'id'=>$child->getId()
                        ));
                    }
                }
                $db=$this->container->getParameter('mdb_base');
                $m=new \Mongo();
                /*
                * Remove images
                */
                $m->$db->Image->remove(
                    array('module.$id'=>new \MongoId($module->getId()))
                );
                /*
                * Remove locations
                */
                $m->$db->Location->remove(
                    array('module.$id'=>new \MongoId($module->getId()))
                );
                /*
                * Remove plantunits
                */
                $m->$db->Plantunit->remove(
                    array('module.$id'=>new \MongoId($module->getId()))
                );
                /*
                * Remove csv file
                */
                $csvfile=__DIR__.'/../../Resources/uploads/'.$collection->getAlias().'/'.$module->getName_fname().'.csv';
                if(file_exists($csvfile))
                {
                    unlink($csvfile);
                }
                /*
                * Remove upload directory
                */
                $dir=$module->getUploaddir();
                if($dir)
                {
                    $dir=$this->get('kernel')->getRootDir().'/../web/uploads/'.$dir;
                    if(file_exists($dir)&&is_dir($dir))
                    {
                        $files=scandir($dir);
                        foreach($files as $file)
                        {
                            if($file!='.'&&$file!='..')
                            {
                                unlink($dir.'/'.$file);
                            }
                        }
                        rmdir($dir);
                    }
                }
                // $dm->remove($module);
                // $dm->flush();
                $m->$db->Module->remove(
                    array('_id'=>new \MongoId($module->getId()))
                );
            }
        }
        return $this->redirect($this->generateUrl('admin_index'));
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm();
    }

    /**
     * @Route("/newmod", name="new_mod")
     * @Template()
     */
    /*
    public function newmodAction()
    {
        $entity = new Modules();
        $form   = $this->createForm(new ModulesType(), $entity);
        $properties = '';
        //$form = $this->container->get('form.factory')->create(new ModulesType());
        return $this->container->get('templating')->renderResponse('PlantnetDataBundle:Backend\Modules:newmod.html.twig', array(
            'entity' => $entity,
            'properties' => $properties,
            'form' => $form->createView()
        ));
    }
    */
}
