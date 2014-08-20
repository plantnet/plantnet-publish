<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Plantnet\DataBundle\Document\Collection,
    Plantnet\DataBundle\Document\Glossary,
    Plantnet\DataBundle\Document\Property,
    Plantnet\DataBundle\Document\Definition;

use Plantnet\DataBundle\Form\GlossaryFormType,
    Plantnet\DataBundle\Form\GlossarySynFormType,
    Plantnet\DataBundle\Form\ImportGlossaryFormType;

use Symfony\Component\Form\FormError;

use Plantnet\DataBundle\Utils\StringHelp;

/**
 * Glossary controller.
 *
 * @Route("/admin/glossary")
 */
class GlossaryController extends Controller
{
    private function getDataBase($user=null,$dm=null)
    {
        if($user){
            return $user->getDbName();
        }
        elseif($dm){
            return $dm->getConfiguration()->getDefaultDB();
        }
        return $this->container->getParameter('mdb_base');
    }

    /**
     * Displays a form to create a new Glossary entity.
     *
     * @Route("/collection/{id}/glossary/new", name="glossary_new")
     * @Template()
     */
    public function glossary_newAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $glossary=new Glossary();
        $form=$this->createForm(new GlossaryFormType(),$glossary);
        return $this->render('PlantnetDataBundle:Backend\Glossary:glossary_new.html.twig',array(
            'collection'=>$collection,
            'glossary'=>$glossary,
            'form'=>$form->createView()
        ));
    }

    /**
     * Creates a new Glossary entity.
     *
     * @Route("/collection/{id}/glossary/create", name="glossary_create")
     * @Method("post")
     * @Template()
     */
    public function glossary_createAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $glossary=new Glossary();
        $request=$this->getRequest();
        $form=$this->createForm(new GlossaryFormType(),$glossary);
        if('POST'===$request->getMethod()){
            $form->bind($request);
            $glossary->setUploaddir($collection->getAlias().'_glossary');
            if($form->isValid()){
                $dm=$this->get('doctrine.odm.mongodb.document_manager');
                $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                $glossary->setCollection($collection);
                $uploadedFile=$glossary->getFile();
                try{
                    $uploadedFile->move(
                        __DIR__.'/../../Resources/uploads/'.$collection->getAlias().'/',
                        'glossary.csv'
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
                $csv=__DIR__.'/../../Resources/uploads/'.$collection->getAlias().'/glossary.csv';
                $handle=fopen($csv,"r");
                $field=fgetcsv($handle,0,";");
                foreach($field as $col){
                    $property=new Property();
                    $cur_encoding=mb_detect_encoding($col);
                    if($cur_encoding=="UTF-8" && mb_check_encoding($col,"UTF-8")){
                        $property->setName($col);
                    }
                    else{
                        $property->setName(utf8_encode($col));
                    }
                    $dm->persist($property);
                    $glossary->addProperties($property);
                }
                $dm->persist($glossary);
                $dm->flush();
                $this->get('session')->getFlashBag()->add('msg_success','Glossary created');
                return $this->redirect($this->generateUrl('glossary_fields_type',array('id'=>$collection->getId())));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Glossary:glossary_new.html.twig',array(
            'collection'=>$collection,
            'glossary'=>$glossary,
            'form'=>$form->createView()
        ));
    }

    /**
     * @Route("/collection/{id}/glossary_fields_selection", name="glossary_fields_type")
     * @Template()
     */
    public function glossary_fields_typeAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $glossary=$collection->getGlossary();
        if(!$glossary){
            throw $this->createNotFoundException('Unable to find Glossary entity.');
        }
        $form=$this->get('form.factory')->create(new ImportGlossaryFormType(),$glossary);
        $count='';
        return $this->render('PlantnetDataBundle:Backend\Glossary:glossary_fields_type.html.twig',array(
            'collection'=>$collection,
            'glossary'=>$glossary,
            'importCount'=>$count,
            'form'=>$form->createView()
        ));
    }

    /**
     * @Route("/collection/{id}/glossary_save_fields", name="glossary_save_fields")
     * @Method("post")
     * @Template()
     */
    public function glossary_save_fieldsAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $glossary=$collection->getGlossary();
        if(!$glossary){
            throw $this->createNotFoundException('Unable to find Glossary entity.');
        }
        $form=$this->createForm(new ImportGlossaryFormType(),$glossary);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            /* à modifier / optimiser / nettoyer / améliorer / ... */
            $required=array(
                'glossary'=>array(
                    'keyword'=>0,
                    'definition'=>0,
                    'file'=>0,
                )
            );
            $data=$request->request->all();
            $data=$data['glossary']['properties'];
            foreach($data as $prop){
                foreach($prop as $key=>$val){
                    if($key=='type'&&!empty($val)){
                        $required['glossary'][$val]=$required['glossary'][$val]+1;
                    }
                }
            }
            $error=false;
            foreach($required['glossary'] as $key=>$val){
                if($val!=1){
                    $error=true;
                }
            }
            $form->bind($request);
            if(!$error){
                if($form->isValid()){
                    $dm=$this->get('doctrine.odm.mongodb.document_manager');
                    $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                    $dm->persist($glossary);
                    $dm->flush();
                    return $this->redirect($this->generateUrl('glossary_import_data',array(
                        'id'=>$id
                    )));
                }
            }
            $this->get('session')->getFlashBag()->add('error','Choose 1 '.implode(', 1 ',array_keys($required['glossary'])));
        }
        $count='';
        return $this->render('PlantnetDataBundle:Backend\Glossary:glossary_fields_type.html.twig',array(
            'collection'=>$collection,
            'glossary'=>$glossary,
            'importCount'=>$count,
            'form'=>$form->createView()
        ));
    }

    /**
     * @Route("/collection/{id}/glossary_import_data", name="glossary_import_data")
     * @Template()
     */
    public function glossary_import_dataAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $glossary=$collection->getGlossary();
        if(!$glossary){
            throw $this->createNotFoundException('Unable to find Glossary entity.');
        }
        $form=$this->get('form.factory')->create(new ImportGlossaryFormType(),$glossary);
        $count='';
        return $this->render('PlantnetDataBundle:Backend\Glossary:glossary_import_data.html.twig',array(
            'collection'=>$collection,
            'glossary'=>$glossary,
            'importCount'=>$count,
            'form'=>$form->createView()
        ));
    }

    /**
     * @Route("/collection/{id}/glossary_importation", name="glossary_importation")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Glossary:glossary_import_glossarydata.html.twig")
     */
    public function glossary_importationAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
        $request=$this->container->get('request');
        set_time_limit(0);
        if($request->isXmlHttpRequest())
        {
            $collection=$dm->getRepository('PlantnetDataBundle:Collection')
                ->findOneBy(array(
                    'id'=>$id
                ));
            if(!$collection){
                throw $this->createNotFoundException('Unable to find Collection entity.');
            }
            $glossary=$collection->getGlossary();
            if(!$glossary){
                throw $this->createNotFoundException('Unable to find Glossary entity.');
            }
            /*
             * Open the uploaded csv
             */
            $csvfile=__DIR__.'/../../Resources/uploads/'.$collection->getAlias().'/glossary.csv';
            $handle=fopen($csvfile,"r");
            /*
             * Get the glossary properties
             */
            $columns=fgetcsv($handle,0,";");
            $fields=array();
            $attributes=$glossary->getProperties();
            foreach($attributes as $field){
                $fields[]=$field;
            }
            /*
             * Initialise the metrics
             */
            //echo "Memory usage before: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
            $s=microtime(true);
            $batchSize=500;
            $rowCount='';
            while(($data=fgetcsv($handle,0,';'))!==FALSE){
                $num=count($data);
                $rowCount++;
                $definition=new Definition();
                $definition->setGlossary($glossary);
                $attributes=array();
                $def_error=false;
                for($c=0;$c<$num;$c++){
                    $value=trim($this->data_encode($data[$c]));
                    $attributes[$fields[$c]->getId()]=$value;
                    switch($fields[$c]->getType()){
                        case 'keyword':
                            if(empty($value)){
                                $def_error=true;
                            }
                            $definition->setName($value);
                            $definition->setDisplayedname($value);
                            break;
                        case 'definition':
                            $definition->setDefinition($value);
                            break;
                        case 'file':
                            $definition->setPath($value);
                            break;
                    }
                }
                if(!$def_error){
                    $definition->setHaschildren(false);
                    $dm->persist($definition);
                }
                if(($rowCount % $batchSize)==0){
                    $dm->flush();
                    $dm->clear();
                    $collection=$dm->getRepository('PlantnetDataBundle:Collection')
                        ->findOneBy(array(
                            'id'=>$id
                        ));
                    $glossary=$collection->getGlossary();
                }
            }
            $dm->persist($glossary);
            $dm->flush();
            $dm->clear();
            //echo "Memory usage after: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
            $e=microtime(true);
            echo ' Inserted '.$rowCount.' objects in '.($e-$s).' seconds'.PHP_EOL;
            fclose($handle);
            if(file_exists($csvfile)){
                unlink($csvfile);
            }
            return $this->container->get('templating')->renderResponse('PlantnetDataBundle:Backend\Glossary:glossary_import_glossarydata.html.twig',array(
                'importCount'=>'Importation Success: '.$rowCount.' objects imported'
            ));
        }else{
            return $this->glossary_import_dataAction($id);
        }
    }

    protected function data_encode($data)
    {
        $data_encoding=mb_detect_encoding($data);
        if($data_encoding=="UTF-8"&&mb_check_encoding($data,"UTF-8")){
            $format=$data;
        }
        else{
            $format=utf8_encode($data);
        }
        return $format;
    }

    /**
     * Displays a form to edit an existing Glossary entity.
     *
     * @Route("/{id}/edit", name="glossary_edit")
     * @Template()
     */
    public function glossary_editAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $glossary=$dm->getRepository('PlantnetDataBundle:Glossary')
            ->find($id);
        if(!$glossary){
            throw $this->createNotFoundException('Unable to find Glossary entity.');
        }
        $collection=$glossary->getCollection();
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $csv=__DIR__.'/../../Resources/uploads/'.$collection->getAlias().'/glossary_syn.csv';
        $deleteSynForm=false;
        if(file_exists($csv)){
            $deleteSynForm=$this->createDeleteSynForm($id);
        }
        $deleteForm=$this->createDeleteForm($id);
        return $this->render('PlantnetDataBundle:Backend\Glossary:glossary_edit.html.twig',array(
            'entity'=>$glossary,
            'delete_syn_form'=>($deleteSynForm!=false)?$deleteSynForm->createView():false,
            'delete_form'=>$deleteForm->createView()
        ));
    }

    /**
     * Deletes a Glossary entity.
     *
     * @Route("/{id}/delete", name="glossary_delete")
     * @Method("post")
     */
    public function glossary_deleteAction($id)
    {
        $form=$this->createDeleteForm($id);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $user=$this->container->get('security.context')->getToken()->getUser();
                $kernel=$this->get('kernel');
                $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:delete glossary '.$id.' '.$user->getDbName().' &> /dev/null &';
                $process=new \Symfony\Component\Process\Process($command);
                $process->start();
                $this->get('session')->getFlashBag()->add('msg_success','Glossary deleted');
            }
        }
        return $this->redirect($this->generateUrl('admin_index'));
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id'=>$id))
            ->add('id','hidden')
            ->getForm();
    }

    private function createDeleteSynForm($id)
    {
        return $this->createFormBuilder(array('id'=>$id))
            ->add('id','hidden')
            ->getForm();
    }

    /**
     * Displays a form to add syn to Glossary entity.
     *
     * @Route("/{id}/syn", name="glossary_syn")
     * @Template()
     */
    public function glossary_synAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $glossary=$dm->getRepository('PlantnetDataBundle:Glossary')
            ->find($id);
        if(!$glossary){
            throw $this->createNotFoundException('Unable to find Glossary entity.');
        }
        $form=$this->createForm(new GlossarySynFormType(),$glossary);
        return $this->render('PlantnetDataBundle:Backend\Glossary:glossary_syn.html.twig',array(
            'glossary'=>$glossary,
            'form'=>$form->createView()
        ));
    }

    /**
     * Add syn to Glossary entity.
     *
     * @Route("/{id}/syn_update", name="glossary_syn_update")
     * @Method("post")
     * @Template()
     */
    public function glossary_syn_updateAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $glossary=$dm->getRepository('PlantnetDataBundle:Glossary')
            ->find($id);
        if(!$glossary){
            throw $this->createNotFoundException('Unable to find Glossary entity.');
        }
        $collection=$glossary->getCollection();
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $request=$this->getRequest();
        $form=$this->createForm(new GlossarySynFormType(),$glossary);
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $uploadedSynFile=$glossary->getSynfile();
                try{
                    $uploadedSynFile->move(
                        __DIR__.'/../../Resources/uploads/'.$collection->getAlias().'/',
                        'glossary_syn.csv'
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
                $kernel=$this->get('kernel');
                $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:glossary syn '.$id.' '.$user->getDbName().' '.$user->getEmail().' &> /dev/null &';
                $process=new \Symfony\Component\Process\Process($command);
                $process->start();
                $this->get('session')->getFlashBag()->add('msg_success','Glossary updated');
                return $this->redirect($this->generateUrl('glossary_edit',array('id'=>$id)));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Glossary:glossary_syn.html.twig',array(
            'glossary'=>$glossary,
            'form'=>$form->createView()
        ));
    }
    
    /**
     * Deletes syn from Glossary entity.
     *
     * @Route("/{id}/syn_delete", name="glossary_syn_delete")
     * @Method("post")
     */
    public function glossary_syn_deleteAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $glossary=$dm->getRepository('PlantnetDataBundle:Glossary')
            ->find($id);
        if(!$glossary){
            throw $this->createNotFoundException('Unable to find Glossary entity.');
        }
        $collection=$glossary->getCollection();
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $csv=__DIR__.'/../../Resources/uploads/'.$collection->getAlias().'/glossary_syn.csv';
        if(!file_exists($csv)){
            throw $this->createNotFoundException('Unable to find Syn entity.');
        }
        $form=$this->createDeleteSynForm($id);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                if(unlink($csv)){
                    $dm=$this->get('doctrine.odm.mongodb.document_manager');
                    $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                    $kernel=$this->get('kernel');
                    $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:glossary unsyn '.$id.' '.$user->getDbName().' '.$user->getEmail().' &> /dev/null &';
                    $process=new \Symfony\Component\Process\Process($command);
                    $process->start();
                    $this->get('session')->getFlashBag()->add('msg_success','Glossary updated');
                }
            }
        }
        return $this->redirect($this->generateUrl('glossary_edit',array('id'=>$id)));
    }
}
