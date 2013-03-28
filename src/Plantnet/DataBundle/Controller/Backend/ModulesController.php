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
    Plantnet\DataBundle\Document\Location,
    Plantnet\DataBundle\Document\Coordinates;

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
    private function getDataBase($user=null,$dm=null)
    {
        if($user)
        {
            return $user->getDbName();
        }
        elseif($dm)
        {
            return $dm->getConfiguration()->getDefaultDB();
        }
        return $this->container->getParameter('mdb_base');
    }

    /**
     * Displays a form to create a new Module entity.
     *
     * @Route("/collection/{id}/module/new/type/{type}", name="module_new")
     * @Template()
     */
    public function module_newAction($id,$type)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = new Module();
        $module->setType($type);
        $idparent = array();
        if($type=='submodule')
        {
            $parents_module = $dm->getRepository('PlantnetDataBundle:Module')
                ->findBy(array(
                    'collection.id' => $collection->getId(),
                    'type' => 'text'
                ));
            foreach($parents_module as $mod){
                $idparent[$mod->getId()] = $mod->getName();
            }
        }
        $form = $this->createForm(new ModuleFormType(), $module, array('idparent' => $idparent));
        return array(
            'idparent' => $idparent,
            'collection' => $collection,
            'module' => $module,
            'form' => $form->createView(),
            'type' => $type
        );
    }

    /**
     * Creates a new Module entity.
     *
     * @Route("/collection/{id}/module/create/type/{type}", name="module_create")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Modules:module_new.html.twig")
     */
    public function module_createAction($id,$type)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = new Module();
        $module->setType($type);
        $request = $this->getRequest();
        $parents_module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findBy(array(
                'collection.id' => $collection->getId(),
                'type' => 'text'
            ));
        $idparent = array();
        foreach($parents_module as $mod){
            $idparent[$mod->getId()] = $mod->getName();
        }
        $form = $this->createForm(new ModuleFormType(), $module, array('idparent' => $idparent));
        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);
            if ($form->isValid()) {
                $dm = $this->get('doctrine.odm.mongodb.document_manager');
                $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
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
            'idparent' => $idparent,
            'collection' => $collection,
            'module' => $module,
            'form' => $form->createView(),
            'type' => $type
        );
    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/fields_selection", name="fields_type")
     * @Template()
     */
    public function fields_typeAction($id, $idmodule)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$idmodule,
                'collection.id' => $collection->getId()
            ));
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
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$idmodule,
                'collection.id' => $collection->getId()
            ));
        if (!$module) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $form = $this->createForm(new ImportFormType(), $module);
        $request = $this->getRequest();
        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);
            if ($form->isValid()) {
                $dm = $this->get('doctrine.odm.mongodb.document_manager');
                $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
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
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if (!$collection) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module = $dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$idmodule,
                'collection.id' => $collection->getId()
            ));
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
            $user=$this->container->get('security.context')->getToken()->getUser();
            $dm=$this->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
            $configuration = $dm->getConnection()->getConfiguration();
            $configuration->setLoggerCallable(null);
            $collection = $dm->getRepository('PlantnetDataBundle:Collection')
                ->findOneBy(array(
                    'id'=>$id
                ));
            if (!$collection) {
                throw $this->createNotFoundException('Unable to find Collection entity.');
            }
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->findOneBy(array(
                    'id'=>$idmodule,
                    'collection.id' => $collection->getId()
                ));
            if (!$module) {
                throw $this->createNotFoundException('Unable to find Module entity.');
            }
            if ($module->getType()=='text')
            {
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
                                case 'title1':
                                    $plantunit->setTitle1($value);
                                    break;
                                case 'title2':
                                    $plantunit->setTitle2($value);
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
                $module->setNbplantunits($rowCount);
                $dm->persist($module);
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
                
                return $this->container->get('templating')->renderResponse('PlantnetDataBundle:Backend\Modules:import_moduledata.html.twig', array(
                    'importCount' => 'Importation Success: '.$rowCount.' objects imported'
                ));
            }
            else
            {
                $kernel=$this->get('kernel');
                $command='php '.$kernel->getRootDir().'/console publish:importation '.$id.' '.$idmodule.' '.$user->getDbName().' '.$user->getEmail().' > /dev/null';
                $process=new \Symfony\Component\Process\Process($command);
                $process->start();

                return $this->container->get('templating')->renderResponse('PlantnetDataBundle:Backend\Modules:import_moduledata.html.twig', array(
                    'importCount' => 'En cours d\'importation, un email vous sera envoyé à la fin du traitement.'
                ));
            }

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
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $entity = $dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        // if($user->getId()!=$entity->getCollection()->getUser()->getId()){
        //     throw $this->createNotFoundException('Unable to find Collection entity.');
        // }
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
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $entity = $dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        // if($user->getId()!=$entity->getCollection()->getUser()->getId()){
        //     throw $this->createNotFoundException('Unable to find Collection entity.');
        // }
        $editForm = $this->createForm(new ModulesType(), $entity);
        $deleteForm = $this->createDeleteForm($id);
        $request = $this->getRequest();
        if ('POST' === $request->getMethod()) {
            $editForm->bindRequest($request);
            if ($editForm->isValid()) {
                $dm = $this->get('doctrine.odm.mongodb.document_manager');
                $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
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
                $user=$this->container->get('security.context')->getToken()->getUser();
                $dm=$this->get('doctrine.odm.mongodb.document_manager');
                $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                $module = $dm->getRepository('PlantnetDataBundle:Module')->find($id);
                if(!$module){
                    throw $this->createNotFoundException('Unable to find Module entity.');
                }
                $collection=$module->getCollection();
                if(!$collection){
                    throw $this->createNotFoundException('Unable to find Collection entity.');
                }
                // if($user->getId()!=$collection->getUser()->getId()){
                //     throw $this->createNotFoundException('Unable to find Collection entity.');
                // }
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
                $db=$this->getDataBase($user);
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
}
