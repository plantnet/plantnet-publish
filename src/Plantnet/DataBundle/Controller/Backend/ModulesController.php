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
    Plantnet\DataBundle\Document\Coordinates,
    Plantnet\DataBundle\Document\Other,
    Plantnet\DataBundle\Document\Taxon;

use Plantnet\DataBundle\Form\ImportFormType,
    Plantnet\DataBundle\Form\Type\ModulesType,
    Plantnet\DataBundle\Form\Type\ModulesTaxoType,
    Plantnet\DataBundle\Form\ModuleFormType,
    Plantnet\DataBundle\Form\ModuleUpdateFormType,
    Plantnet\DataBundle\Form\ModuleDisplaySynsFormType,
    Plantnet\DataBundle\Form\ModuleSynFormType,
    Plantnet\DataBundle\Form\ModuleDescFormType;

use Symfony\Component\Form\FormError;

use Plantnet\DataBundle\Utils\StringHelp;

/**
 * Module controller.
 *
 * @Route("/admin/module")
 */
class ModulesController extends Controller
{

    function mylog($data,$data2=null,$data3=null){
        if( $data != null){
            $this->get('ladybug')->log(func_get_args());
        }
    }

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
     * Displays a form to create a new Module entity.
     *
     * @Route("/collection/{id}/module/new/type/{type}", name="module_new")
     * @Template()
     */
    public function module_newAction($id,$type)
    {
        $this->mylog("module_newAction",$id,$type);

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
        $module=new Module();
        $module->setType($type);
        $idparent=array();
        if($type=='submodule'){
            $parents_module=$dm->getRepository('PlantnetDataBundle:Module')
                ->findBy(array(
                    'collection.id'=>$collection->getId(),
                    'type'=>'text'
                ));
            foreach($parents_module as $mod){
                $idparent[$mod->getId()]=$mod->getName();
            }
        }
        $form=$this->createForm(new ModuleFormType(),$module,array('idparent'=>$idparent));
        return $this->render('PlantnetDataBundle:Backend\Modules:module_new.html.twig',array(
            'idparent'=>$idparent,
            'collection'=>$collection,
            'module'=>$module,
            'form'=>$form->createView(),
            'type'=>$type
        ));
    }

    /**
     * Creates a new Module entity.
     *
     * @Route("/collection/{id}/module/create/type/{type}", name="module_create")
     * @Method("post")
     * @Template()
     */
    public function module_createAction($id,$type)
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
        $module=new Module();
        $module->setType($type);
        $request=$this->getRequest();
        $parents_module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findBy(array(
                'collection.id'=>$collection->getId(),
                'type'=>'text'
            ));
        $idparent=array();
        foreach($parents_module as $mod){
            $idparent[$mod->getId()]=$mod->getName();
        }
        $form=$this->createForm(new ModuleFormType(),$module,array('idparent'=>$idparent));
        if('POST'===$request->getMethod()){

            $form->bind($request);
            $check_name=$module->getName();
            $url=$module->getUrl();
            if(StringHelp::isGoodForUrl($url)){
                $module->setAlias(StringHelp::cleanToPath($url));
                if($module->getType()=='text'){
                    $nb_mods=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                        ->field('collection')->references($collection)
                        ->field('name')->equals($check_name)
                        ->hydrate(true)
                        ->getQuery()
                        ->execute()
                        ->count();
                    $nb_urls=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                        ->field('collection')->references($collection)
                        ->field('url')->equals($url)
                        ->hydrate(true)
                        ->getQuery()
                        ->execute()
                        ->count();
                    $nb_alias=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                        ->field('collection')->references($collection)
                        ->field('alias')->equals($module->getAlias())
                        ->hydrate(true)
                        ->getQuery()
                        ->execute()
                        ->count();
                }
                else{
                    $idsup=$request->request->get('modules');
                    $idsup=$idsup['parent'];
                    $nb_mods=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                        ->field('collection')->references($collection)
                        ->field('parent.id')->equals($idsup)
                        ->field('name')->equals($check_name)
                        ->hydrate(true)
                        ->getQuery()
                        ->execute()
                        ->count();
                    $nb_urls=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                        ->field('collection')->references($collection)
                        ->field('parent.id')->equals($idsup)
                        ->field('url')->equals($url)
                        ->hydrate(true)
                        ->getQuery()
                        ->execute()
                        ->count();
                    $nb_alias=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                        ->field('collection')->references($collection)
                        ->field('parent.id')->equals($idsup)
                        ->field('alias')->equals($module->getAlias())
                        ->hydrate(true)
                        ->getQuery()
                        ->execute()
                        ->count();
                }
                if($nb_mods===0&&$nb_urls===0&&$nb_alias===0){
                    $checked_upload_dir=true;
                    if($module->getType()=='image'){
                        $idparent=$request->request->get('modules');
                        if(array_key_exists('parent',$idparent)&&$idparent['parent']!=null){
                            $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($idparent['parent']);
                            $module->setParent($module_parent);
                        }
                        $module->setUploaddir($collection->getAlias().'_'.$module->getParent()->getAlias().'_'.$module->getAlias());
                        $idsup=$request->request->get('modules');
                        $idsup=$idsup['parent'];
                        $nb_uploaddirs=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                            ->field('collection')->references($collection)
                            ->field('parent.id')->equals($idsup)
                            ->field('uploaddir')->equals($module->getUploaddir())
                            ->hydrate(true)
                            ->getQuery()
                            ->execute()
                            ->count();
                        if($nb_uploaddirs>0){
                            $checked_upload_dir=false;
                        }
                    }elseif($module->getType()=='imageurl'){
                        $idparent=$request->request->get('modules');
                        if(array_key_exists('parent',$idparent)&&$idparent['parent']!=null){
                            $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($idparent['parent']);
                            $module->setParent($module_parent);
                        }
                        $module->setUploaddir($collection->getAlias().'_'.$module->getParent()->getAlias().'_'.$module->getAlias());
                        $idsup=$request->request->get('modules');
                        $idsup=$idsup['parent'];
                        $nb_uploaddirs=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                            ->field('collection')->references($collection)
                            ->field('parent.id')->equals($idsup)
                            ->field('uploaddir')->equals($module->getUploaddir())
                            ->hydrate(true)
                            ->getQuery()
                            ->execute()
                            ->count();
                        if($nb_uploaddirs>0){
                            $checked_upload_dir=false;
                        }

                        $this->mylog("imageurl modules 247 ",$nb_uploaddirs);
                    }
                    if($checked_upload_dir){
                        $this->mylog("checked_upload_dir");
                        if($form->isValid()){
                            $this->mylog("form isvalid");
                            $dm=$this->get('doctrine.odm.mongodb.document_manager');
                            $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                            $module->setCollection($collection);
                            $idparent=$request->request->get('modules');
                            if(array_key_exists('parent',$idparent)&&$idparent['parent']!=null){
                                $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($idparent['parent']);
                                $module->setParent($module_parent);
                            }
                            $module->setType($module->getType());
                            $uploadedFile=$module->getFile();
                            try{
                                $uploadedFile->move(
                                    __DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/',
                                    $module->getAlias().'.csv'
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
                            $csv=__DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'.csv';
                            $handle=fopen($csv, "r");
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
                                $property->setDetails(true);
                                $dm->persist($property);
                                $module->addProperties($property);
                            }
                            fclose($handle);
                            $module->setDeleting(false);
                            $dm->persist($module);
                            $dm->flush();
                            $this->get('session')->getFlashBag()->add('msg_success','Module created');
                            return $this->redirect($this->generateUrl('fields_type',array('id'=>$collection->getId(),'idmodule'=>$module->getId())));
                        }
                    }
                    else{
                        $form->get('url')->addError(new FormError('This value is already used by system (URL or file path).'));
                    }
                }
                else
                {
                    if($nb_mods!=0){
                        $form->get('name')->addError(new FormError('This value is already used at the same tree level.'));
                    }
                    if($nb_urls!=0||$nb_alias!=0){
                        $form->get('url')->addError(new FormError('This value is already used by system (URL or file path).'));
                    }
                }
            }
            else{
                $form->get('url')->addError(new FormError('Illegal characters (allowed \'a-z\', \'0-9\', \'-\', \'_\').'));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Modules:module_new.html.twig',array(
            'idparent'=>$idparent,
            'collection'=>$collection,
            'module'=>$module,
            'form'=>$form->createView(),
            'type'=>$type
        ));
    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/fields_selection", name="fields_type")
     * @Template()
     */
    public function fields_typeAction($id,$idmodule)
    {
        $this->mylog("fields_typeAction",$id,$idmodule);

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
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$idmodule,
                'collection.id'=>$collection->getId()
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $form=$this->get('form.factory')->create(new ImportFormType(),$module);
        $count='';
        return $this->render('PlantnetDataBundle:Backend\Modules:fields_type.html.twig',array(
            'collection'=>$collection,
            'module'=>$module,
            'importCount'=>$count,
            'form'=>$form->createView()
        ));
    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/save_fields", name="save_fields")
     * @Method("post")
     * @Template()
     */
    public function save_fieldsAction($id,$idmodule)
    {
        $this->mylog("save_fieldsAction",$id,$idmodule);

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
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$idmodule,
                'collection.id'=>$collection->getId()
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $form=$this->createForm(new ImportFormType(),$module);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            /* à modifier / optimiser / nettoyer / améliorer / ... */
            $required=array(
                'text'=>array(
                    'idmodule'=>0,
                    'title1'=>0,
                    'title2'=>0,
                ),
                'image'=>array(
                    'idparent'=>0,
                    'file'=>0,
                    'copyright'=>0,
                ),
                'imageurl'=>array(
                    'idparent'=>0,
                    'url'=>0,
                    'copyright'=>0,
                ),
                'locality'=>array(
                    'idparent'=>0,
                    'lon'=>0,
                    'lat'=>0,
                ),
                'other'=>array(
                    'idparent'=>0,
                ),
            );

            $data=$request->request->all();
            $data=$data['modules']['properties'];
            foreach($data as $prop){
                foreach($prop as $key=>$val){

                    if($key=='type'&&!empty($val)){
                        $required[$module->getType()][$val]=$required[$module->getType()][$val]+1;
                    }
                }
            }

            $error=false;
            foreach($required[$module->getType()] as $key=>$val){
                $this->mylog("key val",$key,$val);
                if($val!=1){
                    $error=true;
                }
            }
            $this->mylog("error",$error);
            $form->bind($request);
            if(!$error){
                if($form->isValid()){
                    $dm=$this->get('doctrine.odm.mongodb.document_manager');
                    $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                    $dm->persist($module);
                    $dm->flush();
                    $this->update_indexes($module);
                    return $this->redirect($this->generateUrl('import_data',array(
                        'id'=>$id,
                        'idmodule'=>$idmodule
                    )));
                }
            }
            $this->get('session')->getFlashBag()->add('error','Choose 1 '.implode(', 1 ',array_keys($required[$module->getType()])));
        }
        $count='';
        return $this->render('PlantnetDataBundle:Backend\Modules:fields_type.html.twig',array(
            'collection'=>$collection,
            'module'=>$module,
            'importCount'=>$count,
            'form'=>$form->createView()
        ));
    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/import_data", name="import_data")
     * @Template()
     */
    public function import_dataAction($id,$idmodule)
    {
        $this->mylog("import_dataAction",$id,$idmodule);

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
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$idmodule,
                'collection.id'=>$collection->getId()
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $form=$this->get('form.factory')->create(new ImportFormType(),$module);
        $count='';
        return $this->render('PlantnetDataBundle:Backend\Modules:import_data.html.twig',array(
            'collection'=>$collection,
            'module'=>$module,
            'importCount'=>$count,
            'form'=>$form->createView()
        ));
    }

    /**
     * @Route("/collection/{id}/module/{idmodule}/importation", name="importation")
     * @Method("post")
     * @Template("PlantnetDataBundle:Backend\Modules:import_moduledata.html.twig")
     */
    public function importationAction($id, $idmodule)
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
            $module=$dm->getRepository('PlantnetDataBundle:Module')
                ->findOneBy(array(
                    'id'=>$idmodule,
                    'collection.id'=>$collection->getId()
                ));
            if(!$module){
                throw $this->createNotFoundException('Unable to find Module entity.');
            }
            if($module->getType()=='text'){
                /*
                 * Open the uploaded csv
                 */
                $csvfile=__DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'.csv';
                $handle=fopen($csvfile,"r");
                /*
                 * Get the module properties
                 */
                $columns=fgetcsv($handle,0,";");
                $fields=array();
                $attributes=$module->getProperties();
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
                    if($module->getType()=='text'){
                        $plantunit=new Plantunit();
                        $plantunit->setModule($module);
                        $attributes=array();
                        for($c=0;$c<$num;$c++){
                            $value=trim($this->data_encode($data[$c]));
                            //check for int or float value
                            if(is_numeric($value)){
                                $tmp_value=intval($value);
                                if($value==$tmp_value){
                                    $value=$tmp_value;
                                }
                                else{
                                    $tmp_value=floatval($value);
                                    if($value==$tmp_value){
                                        $value=$tmp_value;
                                    }
                                }
                            }
                            //
                            $attributes[$fields[$c]->getId()]=$value;
                            switch($fields[$c]->getType()){
                                case 'idmodule':
                                    $plantunit->setIdentifier($value.'');
                                    break;
                                case 'idparent':
                                    $plantunit->setIdparent($value.'');
                                    break;
                                case 'title1':
                                    $plantunit->setTitle1($value.'');
                                    break;
                                case 'title2':
                                    $plantunit->setTitle2($value.'');
                                    break;
                                case 'title3':
                                    $plantunit->setTitle3($value.'');
                                    break;
                            }
                        }
                        $plantunit->setAttributes($attributes);
                        $plantunit->setHasimages(false);
                        $plantunit->setHaslocations(false);
                        $dm->persist($plantunit);
                    }
                    if(($rowCount % $batchSize)==0){
                        $dm->flush();
                        $dm->clear();
                        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                    }
                }
                $module->setNbrows($rowCount);
                $dm->persist($module);
                $dm->flush();
                $dm->clear();
                //echo "Memory usage after: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
                $e=microtime(true);
                echo ' Inserted '.$rowCount.' objects in '.($e-$s).' seconds'.PHP_EOL;
                fclose($handle);
                if(file_exists($csvfile)){
                    unlink($csvfile);
                }
                return $this->container->get('templating')->renderResponse('PlantnetDataBundle:Backend\Modules:import_moduledata.html.twig',array(
                    'importCount'=>'Importation Success: '.$rowCount.' objects imported'
                ));
            }
            else
            {
                $module->setUpdating(true);
                $dm->persist($module);
                $dm->flush();
                $kernel=$this->get('kernel');
                $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:importation '.$id.' '.$idmodule.' '.$user->getDbName().' '.$user->getEmail().' >symfonyalain.log &';

                // echo "<br>ModulesController.php:importationAction:  ".$command;

                $process=new \Symfony\Component\Process\Process($command);
                $process->start();
                return $this->container->get('templating')->renderResponse('PlantnetDataBundle:Backend\Modules:import_moduledata.html.twig',array(
                    'importCount'=>'Importing data in progress, an email will be sent at the end of task.'
                ));
            }
        }else{
            return $this->import_dataAction($id, $idmodule);
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
     * Displays a form to edit an existing Module entity.
     *
     * @Route("/{id}/edit", name="module_edit")
     * @Template()
     */
    public function module_editAction($id)
    {
        $this->mylog("module_editAction",$id);

        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $editForm=$this->get('form.factory')->create(new ModulesType(),$module);
        $deleteForm=$this->createDeleteForm($id);
        return $this->render('PlantnetDataBundle:Backend\Modules:module_edit.html.twig',array(
            'entity'=>$module,
            'edit_form'=>$editForm->createView(),
            'delete_form'=>$deleteForm->createView(),
        ));
    }

    /**
     * Edits an existing Module entity.
     *
     * @Route("/{id}/update", name="module_update")
     * @Method("post")
     * @Template()
     */
    public function module_updateAction($id)
    {
        $this->mylog("module_updateAction",$id);

        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $collection=$module->getCollection();
        $original_name=$module->getName();
        $editForm=$this->createForm(new ModulesType(),$module);
        $deleteForm=$this->createDeleteForm($id);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $editForm->bind($request);
            $check_name=$module->getName();
            $url=$module->getUrl();
            if(StringHelp::isGoodForUrl($url)){
                if($module->getType()=='text'){
                    $nb_mods=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                        ->field('collection')->references($collection)
                        ->field('name')->equals($check_name)
                        ->field('id')->notEqual($module->getId())
                        ->hydrate(true)
                        ->getQuery()
                        ->execute()
                        ->count();
                    $nb_urls=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                        ->field('collection')->references($collection)
                        ->field('url')->equals($url)
                        ->field('id')->notEqual($module->getId())
                        ->hydrate(true)
                        ->getQuery()
                        ->execute()
                        ->count();
                }
                else{
                    $idsup=$request->request->get('modules');
                    $idsup=$idsup['parent'];
                    $nb_mods=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                        ->field('collection')->references($collection)
                        ->field('parent.id')->equals($idsup)
                        ->field('name')->equals($check_name)
                        ->field('id')->notEqual($module->getId())
                        ->hydrate(true)
                        ->getQuery()
                        ->execute()
                        ->count();
                    $nb_urls=$dm->createQueryBuilder('PlantnetDataBundle:Module')
                        ->field('collection')->references($collection)
                        ->field('parent.id')->equals($idsup)
                        ->field('url')->equals($url)
                        ->field('id')->notEqual($module->getId())
                        ->hydrate(true)
                        ->getQuery()
                        ->execute()
                        ->count();
                }
                if($nb_mods===0&&$nb_urls===0){
                    if($editForm->isValid()){
                        $dm=$this->get('doctrine.odm.mongodb.document_manager');
                        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                        $dm->persist($module);
                        $dm->flush();
                        $this->update_indexes($module);
                        $this->get('session')->getFlashBag()->add('msg_success','Module updated');
                        return $this->redirect($this->generateUrl('module_edit',array('id'=>$id)));
                    }
                }
                else{
                    if($nb_mods!=0){
                        $module->setName($original_name);
                        $editForm->get('name')->addError(new FormError('This value is already used at the same tree level.'));
                    }
                    if($nb_urls!=0){
                        $module->setUrl($url);
                        $editForm->get('url')->addError(new FormError('This value is already used by system (URL or file path).'));
                    }
                }
            }
            else{
                $editForm->get('url')->addError(new FormError('Illegal characters (allowed \'a-z\', \'0-9\', \'-\', \'_\').'));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Modules:module_edit.html.twig',array(
            'entity'=>$module,
            'edit_form'=>$editForm->createView(),
            'delete_form'=>$deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing Module entity.
     *
     * @Route("/{id}/edit_taxo", name="module_edit_taxo")
     * @Template()
     */
    public function module_edit_taxoAction($id)
    {
        $this->mylog("module_edit_taxoAction",$id);

        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $editForm=$this->get('form.factory')->create(new ModulesTaxoType(),$module);
        $editDisplaySynsForm=$this->get('form.factory')->create(new ModuleDisplaySynsFormType(),$module);
        $deleteSynForm=false;
        $deleteDescForm=false;
        $csv=__DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'_syn.csv';
        if(file_exists($csv)){
            $deleteSynForm=$this->createDeleteSynForm($id);
        }
        $csv=__DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'_desc.csv';
        if(file_exists($csv)){
            $deleteDescForm=$this->createDeleteDescForm($id);
        }
        $nb_taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->field('module')->references($module)
            ->field('parent')->equals(null)
            ->field('issynonym')->equals(false)
            ->getQuery()
            ->count();
        return $this->render('PlantnetDataBundle:Backend\Modules:module_edit_taxo.html.twig',array(
            'entity'=>$module,
            'nb_taxons'=>$nb_taxons,
            'edit_form'=>$editForm->createView(),
            'edit_display_syns_form'=>$editDisplaySynsForm->createView(),
            'delete_syn_form'=>($deleteSynForm!=false)?$deleteSynForm->createView():false,
            'delete_desc_form'=>($deleteDescForm!=false)?$deleteDescForm->createView():false
        ));
    }

    /**
     * Edits an existing Module entity.
     *
     * @Route("/{id}/update_taxo", name="module_update_taxo")
     * @Method("post")
     * @Template()
     */
    public function module_update_taxoAction($id)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $collection=$module->getCollection();
        $editForm=$this->createForm(new ModulesTaxoType(),$module);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $editForm->bind($request);
            if($editForm->isValid()){
                $dm=$this->get('doctrine.odm.mongodb.document_manager');
                $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                foreach($module->getProperties() as $prop){
                    if($prop->getTaxolevel()&&!$prop->getTaxolabel()){
                        $prop->setTaxolabel($prop->getName());
                        $dm->persist($prop);
                    }
                }
                $module->setUpdating(true);
                $dm->persist($module);
                $dm->flush();
                //command
                $kernel=$this->get('kernel');
                $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:taxon taxo '.$id.' '.$user->getDbName().' '.$user->getEmail().' clean >symfonyalain.log &';

                echo "<br>ModulesController.php:module_update_taxoAction:  ".$command;
                $this->mylog("module_update_taxoAction",$command);

                $process=new \Symfony\Component\Process\Process($command);
                $process->start();
                $this->get('session')->getFlashBag()->add('msg_success','Taxonomy updated');
                return $this->redirect($this->generateUrl('module_edit_taxo',array('id'=>$id)));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Modules:module_edit_taxo.html.twig',array(
            'entity'=>$module,
            'edit_form'=>$editForm->createView(),
        ));
    }

    /**
     * Edits an existing Module entity.
     *
     * @Route("/{id}/update_taxo_display_syns", name="module_update_taxo_display_syns")
     * @Method("post")
     * @Template()
     */
    public function module_update_taxo_display_synsAction($id)
    {

        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $collection=$module->getCollection();
        $editForm=$this->get('form.factory')->create(new ModuleDisplaySynsFormType(),$module);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $editForm->bind($request);
            if($editForm->isValid()){
                $dm=$this->get('doctrine.odm.mongodb.document_manager');
                $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                $dm->persist($module);
                $dm->flush();
                $this->get('session')->getFlashBag()->add('msg_success','Taxonomy updated');
                return $this->redirect($this->generateUrl('module_edit_taxo',array('id'=>$id)));
            }
        }
        return $this->redirect($this->generateUrl('module_edit_taxo',array('id'=>$id)));
    }

    private function createDeleteSynForm($id)
    {
        $this->mylog("createDeleteSynForm",$id);

        return $this->createFormBuilder(array('id'=>$id))
            ->add('id','hidden')
            ->getForm();
    }

    private function createDeleteDescForm($id)
    {
        $this->mylog("createDeleteDescForm",$id);
        return $this->createFormBuilder(array('id'=>$id))
            ->add('id','hidden')
            ->getForm();
    }
    
    /**
     * Displays a form to add syns to Module entity.
     *
     * @Route("/{id}/syn", name="module_syn")
     * @Template()
     */
    public function module_synAction($id)
    {
        $this->mylog("module_synAction",$id);

        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $form=$this->createForm(new ModuleSynFormType(),$module);
        return $this->render('PlantnetDataBundle:Backend\Modules:module_syn.html.twig',array(
            'module'=>$module,
            'form'=>$form->createView()
        ));
    }

    /**
     * Displays a form to add taxa desc to Module entity.
     *
     * @Route("/{id}/desc", name="module_desc")
     * @Template()
     */
    public function module_descAction($id)
    {
        $this->mylog("module_descAction",$id);

        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $form=$this->createForm(new ModuleDescFormType(),$module);
        return $this->render('PlantnetDataBundle:Backend\Modules:module_desc.html.twig',array(
            'module'=>$module,
            'form'=>$form->createView()
        ));
    }

    /**
     * Add syn to Module entity.
     *
     * @Route("/{id}/syn_update", name="module_syn_update")
     * @Method("post")
     * @Template()
     */
    public function module_syn_updateAction($id)
    {

        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $request=$this->getRequest();
        $form=$this->createForm(new ModuleSynFormType(),$module);
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $uploadedSynFile=$module->getSynfile();
                try{
                    $uploadedSynFile->move(
                        __DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/',
                        $module->getAlias().'_syn.csv'
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
                $module->setUpdating(true);
                $dm->persist($module);
                $dm->flush();
                $kernel=$this->get('kernel');
                $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:taxon syn '.$id.' '.$user->getDbName().' '.$user->getEmail().' >symfonyalain.log &';

                //echo "<br>ModulesController.php:module_syn_updateAction:  ".$command;

                $process=new \Symfony\Component\Process\Process($command);
                $process->start();
                $this->get('session')->getFlashBag()->add('msg_success','Taxonomy updated');
                return $this->redirect($this->generateUrl('module_syn',array('id'=>$module->getId())));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Modules:module_syn.html.twig',array(
            'module'=>$module,
            'form'=>$form->createView()
        ));
    }

    /**
     * Add taxa desc to Module entity.
     *
     * @Route("/{id}/desc_update", name="module_desc_update")
     * @Method("post")
     * @Template()
     */
    public function module_desc_updateAction($id)
    {
        $this->mylog("module_desc_updateAction",$id);

        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $request=$this->getRequest();
        $form=$this->createForm(new ModuleDescFormType(),$module);
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $uploadedDescFile=$module->getDescfile();
                try{
                    $uploadedDescFile->move(
                        __DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/',
                        $module->getAlias().'_desc.csv'
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
                $module->setUpdating(true);
                $dm->persist($module);
                $dm->flush();
                $kernel=$this->get('kernel');
                $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:taxon desc '.$id.' '.$user->getDbName().' '.$user->getEmail().' >symfonyalain.log &';

                // echo "<br>ModulesController.php:module_desc_updateAction:  ".$command;

                $process=new \Symfony\Component\Process\Process($command);
                $process->start();
                $this->get('session')->getFlashBag()->add('msg_success','Taxonomy updated');
                return $this->redirect($this->generateUrl('module_desc',array('id'=>$module->getId())));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Modules:module_desc.html.twig',array(
            'module'=>$module,
            'form'=>$form->createView()
        ));
    }

    /**
     * Deletes syn from Module entity.
     *
     * @Route("/{id}/syn_delete", name="module_syn_delete")
     * @Method("post")
     */
    public function module_syn_deleteAction($id)
    {
        $this->mylog("module_syn_deleteAction",$id);

        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $csv=__DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'_syn.csv';
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
                    $module->setUpdating(true);
                    $dm->persist($module);
                    $dm->flush();
                    //command
                    $kernel=$this->get('kernel');
                    $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:taxon taxo '.$id.' '.$user->getDbName().' '.$user->getEmail().' >symfonyalain.log &';

                    //echo "<br>ModulesController.php:module_syn_deleteAction:  ".$command;

                    $process=new \Symfony\Component\Process\Process($command);
                    $process->start();
                    $this->get('session')->getFlashBag()->add('msg_success','Taxonomy updated');
                }
            }
        }
        return $this->redirect($this->generateUrl('module_edit_taxo',array('id'=>$id)));
    }

    /**
     * Deletes taxa desc from Module entity.
     *
     * @Route("/{id}/desc_delete", name="module_desc_delete")
     * @Method("post")
     */
    public function module_desc_deleteAction($id)
    {
        $this->mylog("module_desc_deleteAction",$id);

        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $csv=__DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'_desc.csv';
        if(!file_exists($csv)){
            throw $this->createNotFoundException('Unable to find Desc entity.');
        }
        $form=$this->createDeleteDescForm($id);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                if(unlink($csv)){
                    $dm=$this->get('doctrine.odm.mongodb.document_manager');
                    $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                    $module->setUpdating(true);
                    $dm->persist($module);
                    $dm->flush();
                    //command
                    $kernel=$this->get('kernel');
                    $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:taxon undesc '.$id.' '.$user->getDbName().' '.$user->getEmail().' >symfonyalain.log &';

                    //echo "<br>ModulesController.php:module_desc_deleteAction:  ".$command;

                    $process=new \Symfony\Component\Process\Process($command);
                    $process->start();
                    $this->get('session')->getFlashBag()->add('msg_success','Taxonomy updated');
                }
            }
        }
        return $this->redirect($this->generateUrl('module_edit_taxo',array('id'=>$id)));
    }

    /**
     * Deletes a Module entity.
     *
     * @Route("/{id}/delete", name="module_delete")
     * @Method("post")
     */
    public function module_deleteAction($id)
    {


        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $form=$this->createDeleteForm($id);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $module->setDeleting(true);
                $dm->persist($module);
                $dm->flush();
                $user=$this->container->get('security.context')->getToken()->getUser();
                $kernel=$this->get('kernel');
                $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:delete module '.$id.' '.$user->getDbName().' >symfonyalain.log &';

                $this->mylog("module_deleteAction",$command);

                $process=new \Symfony\Component\Process\Process($command);
                $process->start();

                $this->get('session')->getFlashBag()->add('msg_success','Module deleted');
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

    private function update_indexes($module)
    {
        $this->mylog("update_indexes",$module);


        if($module){
            ini_set('memory_limit','-1');
            $user=$this->container->get('security.context')->getToken()->getUser();
            $dm=$this->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
            $db=$this->getDataBase($user);
            $m=new \MongoClient($this->container->getParameter('mdb_connection_url'));
            \MongoCursor::$timeout=-1;
            //old indexes
            $old_indexes=$module->getIndexes();
            if(count($old_indexes)){
                foreach($old_indexes as $old){
                    //delete old indexes
                    $m->$db->command(array('deleteIndexes'=>'Plantunit','index'=>$old));
                }
            }
            //get sort order
            $fake_order=100;
            $order=array();
            $field=$module->getProperties();
            foreach($field as $row){
                if($row->getMain()==true){
                    if(!$row->getSortorder()){
                        $order[$fake_order++]=$row->getId();
                    }
                    else{
                        $order[$row->getSortorder()]=$row->getId();
                    }
                }
                elseif($row->getSortorder()){
                    $order[$row->getSortorder()]=$row->getId();
                }
            }
            ksort($order);
            //init indexes tab
            $indexes_tab=array();
            //indexes [asc]
            foreach($order as $o=>$id){
                $index=array();
                $index['attributes.'.$id]=1;
                foreach($order as $sub_o=>$sub_id){
                    if($id!=$sub_id){
                        $index['attributes.'.$sub_id]=1;
                    }
                }
                $indexes_tab[]=$index;
            }
            //indexes [desc]
            foreach($order as $o=>$id){
                $index=array();
                $index['attributes.'.$id]=-1;
                foreach($order as $sub_o=>$sub_id){
                    if($id!=$sub_id){
                        $index['attributes.'.$sub_id]=1;
                    }
                }
                $indexes_tab[]=$index;
            }
            //format indexes
            $indexes=array();
            $index_name=1;
            foreach($indexes_tab as $index){
                $name=$module->getId().'_punit_sort_'.$index_name++;
                $indexes[]=$name;
                //new indexes
                $m->$db->Plantunit->ensureIndex($index,array('name'=>$name));
            }
            $module->setIndexes($indexes);
            $dm->persist($module);
            $dm->flush();
        }
    }

    /**
     * Displays a form to edit data for existing Module entity.
     *
     * @Route("/{id}/edit_data", name="module_edit_data")
     * @Template()
     */
    public function module_edit_dataAction($id)
    {
        $this->mylog("module_edit_dataAction",$id);

        $limit=200000;
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $is_over=false;
        if($module->getNbrows()>$limit){
            $is_over=true;
            $this->get('session')->getFlashBag()->add('error','This action is disabled.');
        }
        $form=$this->createForm(new ModuleUpdateFormType(),$module);
        return $this->render('PlantnetDataBundle:Backend\Modules:module_edit_data.html.twig',array(
            'module'=>$module,
            'is_over'=>$is_over,
            'form'=>$form->createView(),
        ));
    }

    /**
     * Edits data for existing Module entity.
     *
     * @Route("/{id}/update_data", name="module_update_data")
     * @Method("post")
     * @Template()
     */
    public function module_update_dataAction($id)
    {
        $limit=200000;
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->find($id);
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $is_over=true;
        if($module->getNbrows()<=$limit){
            $is_over=false;
            $properties=$module->getProperties();
            $nb_properties=count($properties);
            $request=$this->getRequest();
            $form=$this->createForm(new ModuleUpdateFormType(),$module);
            if('POST'===$request->getMethod()){
                $form->bind($request);
                $uploadedFile=$module->getFile();
                $old_csv=__DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'.csv';
                if(file_exists($old_csv)){
                    unlink($old_csv);
                }
                unset($old_csv);
                try{
                    $uploadedFile->move(
                        __DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/',
                        $module->getAlias().'.csv'
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
                $csv=__DIR__.'/../../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'.csv';
                $handle=fopen($csv, "r");
                $field=fgetcsv($handle,0,";");
                $nb_columns=0;
                foreach($field as $col){
                    $nb_columns++;
                }
                fclose($handle);
                if($nb_columns==$nb_properties){
                    $module->setUpdating(true);
                    $dm->persist($module);
                    $dm->flush();
                    $user=$this->container->get('security.context')->getToken()->getUser();
                    $kernel=$this->get('kernel');
                    $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:update '.$module->getId().' '.$user->getDbName().' '.$user->getEmail().' >symfonyalain.log &';

                    //echo "<br>ModulesController.php:module_update_dataAction:  ".$command;

                    $process=new \Symfony\Component\Process\Process($command);
                    $process->start();
                    $this->get('session')->getFlashBag()->add('msg_success','Updating data.');
                }
                else{
                    if(file_exists($csv)){
                        unlink($csv);
                    }
                    $this->get('session')->getFlashBag()->add('error','The number of columns in the CSV file does not match the number of columns in this module.');
                    $form->get('file')->addError(new FormError('The number of columns in the CSV file does not match the number of columns in this module.'));
                }
            }
        }
        else{
            $this->get('session')->getFlashBag()->add('error','This action is disabled.');
        }
        return $this->render('PlantnetDataBundle:Backend\Modules:module_edit_data.html.twig',array(
            'module'=>$module,
            'is_over'=>$is_over,
            'form'=>$form->createView()
        ));
    }
}
