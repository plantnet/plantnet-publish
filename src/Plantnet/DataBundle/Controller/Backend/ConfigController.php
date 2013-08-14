<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Plantnet\DataBundle\Document\Database,
    Plantnet\DataBundle\Document\Config,
    Plantnet\DataBundle\Form\Type\ConfigType,
    Plantnet\DataBundle\Form\Type\ConfigImageType,
    Plantnet\DataBundle\Form\Type\ConfigTemplateType;

/**
 * Config controller.
 *
 * @Route("/admin/config")
 */
class ConfigController extends Controller
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
     * Displays a form to edit Config entity.
     *
     * @Route("/edit_banner", name="config_edit_banner")
     * @Template()
     */
    public function config_edit_bannerAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $this->createNotFoundException('Unable to find Config entity.');
        }
        $editForm=$this->createForm(new ConfigImageType(),$config);
        $deleteForm=$this->createDeleteBannerForm();
        return $this->render('PlantnetDataBundle:Backend\Config:config_edit_banner.html.twig',array(
            'entity'=>$config,
            'edit_form'=>$editForm->createView(),
            'delete_form'=>$deleteForm->createView(),
            'current'=>'config'
        ));
    }

    /**
     * Edits Config entity.
     *
     * @Route("/update_banner", name="config_update_banner")
     * @Method("post")
     * @Template()
     */
    public function config_update_bannerAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $this->createNotFoundException('Unable to find Config entity.');
        }
        $old_banner=$config->getFilepath();
        if($old_banner&&file_exists($this->get('kernel')->getRootDir().'/../web/'.$old_banner)){
            $config->setFilepath('');
            unlink($this->get('kernel')->getRootDir().'/../web/'.$old_banner);
            $dm->persist($config);
            $dm->flush();
        }
        $editForm=$this->createForm(new ConfigImageType(),$config);
        $deleteForm=$this->createDeleteBannerForm();
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $editForm->bind($request);
            $banner=$config->getFile();
            try{
                $new_name=$this->getDataBase($user,$dm).'.'.$banner->guessExtension();
                $banner->move(
                    $this->get('kernel')->getRootDir().'/../web/banners/',
                    $new_name
                );
            }
            catch(FilePermissionException $e)
            {
                throw new \Exception($e->getMessage());
            }
            catch(\Exception $e)
            {
                throw new \Exception($e->getMessage());
            }
            $config->setFilepath('banners/'.$new_name);
            $dm->persist($config);
            $dm->flush();
            $this->get('session')->getFlashBag()->add('msg_success','Banner updated');
        }
        return $this->render('PlantnetDataBundle:Backend\Config:config_edit_banner.html.twig',array(
            'entity'=>$config,
            'edit_form'=>$editForm->createView(),
            'delete_form'=>$deleteForm->createView(),
            'current'=>'config'
        ));
    }

    private function createDeleteBannerForm()
    {
        return $this->createFormBuilder(array('delete_banner'=>1))
            ->add('delete_banner','hidden')
            ->getForm();
    }

    /**
     * Deletes banner from Config entity.
     *
     * @Route("/delete_banner", name="config_delete_banner")
     * @Method("post")
     */
    public function config_delete_bannerAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $this->createNotFoundException('Unable to find Config entity.');
        }
        $form=$this->createDeleteBannerForm();
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $old_banner=$config->getFilepath();
                if($old_banner&&file_exists($this->get('kernel')->getRootDir().'/../web/'.$old_banner)){
                    $config->setFilepath('');
                    unlink($this->get('kernel')->getRootDir().'/../web/'.$old_banner);
                    $dm->persist($config);
                    $dm->flush();
                    $this->get('session')->getFlashBag()->add('msg_success','Banner deleted');
                }
            }
        }
        return $this->redirect($this->generateUrl('config_edit_banner'));
    }

    /**
     * Displays a form to edit Config entity.
     *
     * @Route("/edit", name="config_edit")
     * @Template()
     */
    public function config_editAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $this->createNotFoundException('Unable to find Config entity.');
        }
        $editForm=$this->createForm(new ConfigType(),$config);
        return $this->render('PlantnetDataBundle:Backend\Config:config_edit.html.twig',array(
            'entity'=>$config,
            'edit_form'=>$editForm->createView(),
            'current'=>'config'
        ));
    }

    /**
     * Edits Config entity.
     *
     * @Route("/update", name="config_update")
     * @Method("post")
     * @Template()
     */
    public function config_updateAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $this->createNotFoundException('Unable to find Config entity.');
        }
        if($config->getIslocked()!=true){
            $editForm=$this->createForm(new ConfigType(),$config);
            $request=$this->getRequest();
            if('POST'===$request->getMethod()){
                $editForm->bind($request);
                // supprimer la langue par defaut des langues dispo
                $default=$config->getDefaultlanguage();
                $availables=$config->getAvailablelanguages();
                if(count($availables)){
                    foreach($availables as $key=>$available){
                        if($available==$default){
                            unset($availables[$key]);
                        }
                    }
                    if(count($availables)){
                        $availables=array_values($availables);
                        $this->check_databases($availables,$user,$default);
                    }
                    $config->setAvailablelanguages($availables);
                }
                $dm->persist($config);
                $dm->flush();
                $this->get('session')->getFlashBag()->add('msg_success','Languages updated');
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Config:config_edit.html.twig',array(
            'entity'=>$config,
            'edit_form'=>$editForm->createView(),
            'current'=>'config'
        ));
    }

    private function check_databases($languages,$user,$default)
    {
        $default_db=$user->getDbName();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase());
        $database=$dm->getRepository('PlantnetDataBundle:Database')
            ->findOneBy(array(
                'name'=>$default_db,
                'language'=>$default
            ));
        if(!$database){
            throw $this->createNotFoundException('Unable to find Database entity.');
        }
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $this->createNotFoundException('Unable to find Config entity.');
        }
        $default_template=$config->getTemplate();
        $default_link=$database->getLink();
        $children=$database->getChildren();
        if(count($children)){
            foreach($children as $child){
                if(in_array($child->getLanguage(),$languages)){
                    $child->setEnable(true);
                    $dm->persist($child);
                }
                else{
                    $child->setEnable(false);
                    $dm->persist($child);
                }
            }
            $dm->flush();
        }
        foreach($languages as $language){
            $exists=false;
            if(count($children)){
                foreach($children as $child){
                    if($language==$child->getLanguage()){
                        $exists=true;
                    }
                }
            }
            if(!$exists){
                $new_name=$default_db.'_'.$language;
                $new_database=new Database();
                $new_database->setName($new_name);
                $new_database->setDisplayedname(ucfirst($default_link).' '.$language);
                $new_database->setLink($default_link.'_'.$language);
                $new_database->setLanguage($language);
                $new_database->setEnable(true);
                $new_database->setParent($database);
                $dm->persist($new_database);
                $dm->flush();
                //create new database
                $connection=new \MongoClient();
                $db=$connection->$new_name;
                $db->listCollections();
                //collections
                $db->createCollection('Collection');
                $db->createCollection('Config');
                $db->createCollection('Definition');
                $db->createCollection('Glossary');
                $db->createCollection('Image');
                $db->createCollection('Location');
                $db->createCollection('Other');
                $db->createCollection('Module');
                $db->createCollection('Plantunit');
                $db->createCollection('Taxon');
                $db->createCollection('Page');
                //indexes
                $db->Image->ensureIndex(array("title1"=>1,"title2"=>1));
                $db->Location->ensureIndex(array("coordinates"=>"2d"));
                // $db->Plantunit->ensureIndex(array("attributes"=>"text"));
                $db->Taxon->ensureIndex(array("name"=>1));
                //pages data
                $db->Page->insert(array('name'=>'Home','alias'=>'home','order'=>1));
                $db->Page->insert(array('name'=>'Mentions','alias'=>'mentions','order'=>2));
                $db->Page->insert(array('name'=>'Credits','alias'=>'credits','order'=>3));
                $db->Page->insert(array('name'=>'Contacts','alias'=>'contacts','order'=>4));
                //init config
                $db->Config->insert(array(
                    'islocked'=>true,
                    'originaldb'=>$default_db,
                    'defaultlanguage'=>$language,
                    'name'=>ucfirst($default_link).' '.$language,
                    'template'=>$default_template
                ));
            }
        }
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
    }

    public function language_listAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase());
        $database=$dm->getRepository('PlantnetDataBundle:Database')
            ->findOneBy(array(
                'name'=>$user->getDbName()
            ));
        if(!$database){
            throw $this->createNotFoundException('Unable to find Database entity.');
        }
        $parent=$database->getParent();
        if($parent){
            $database=$parent;
        }
        $availables=array();
        $children=$database->getChildren();
        if(count($children)){
            foreach($children as $child){
                if($child->getEnable()==true){
                    $availables[]=$child->getLanguage();
                }
            }
        }
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        return $this->render('PlantnetDataBundle:Backend\Config:language_list.html.twig',array(
            'default'=>$database->getLanguage(),
            'availables'=>$availables,
            'current'=>'administration'
        ));
    }

    /**
     * @Route("/language_switch/{lang}", name="config_language_switch")
     * @Template()
     */
    public function config_language_switchAction($lang)
    {
        $userManager=$this->get('fos_user.user_manager');
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase());
        $database=$dm->getRepository('PlantnetDataBundle:Database')
            ->findOneBy(array(
                'name'=>$user->getDbName()
            ));
        if(!$database){
            throw $this->createNotFoundException('Unable to find Database entity.');
        }
        $parent=$database->getParent();
        if($parent){
            $database=$parent;
        }
        $default=$database->getLanguage();
        $availables=array();
        $children=$database->getChildren();
        if(count($children)){
            foreach($children as $child){
                if($child->getEnable()==true){
                    $availables[]=$child->getLanguage();
                }
            }
        }
        $default_db=$database->getName();
        $user->setDbName($default_db);
        if($lang!=$default){
            if(in_array($lang,$availables)){
                $user->setDbName($default_db.'_'.$lang);
            }
        }
        $userManager->updateUser($user);
        $this->get('session')->getFlashBag()->add('msg_success','Switched to '.\Locale::getDisplayName($lang).' content');
        return $this->redirect($this->generateUrl('admin_index'));
    }

    private function template_list()
    {
        $templates=array();
        $dir=__DIR__.'/../../Resources/views';
        if(file_exists($dir)&&is_dir($dir)){
            $files=scandir($dir);
            foreach($files as $file){
                if($file!='.'&&$file!='..'&&is_dir($dir.'/'.$file)&&substr_count($file,'Frontend')==1){
                    $templates[$file]=$file;
                }
            }
        }
        return $templates;
    }

    /**
     * Displays a form to edit Config entity.
     *
     * @Route("/edit_template", name="config_edit_template")
     * @Template()
     */
    public function config_edit_templateAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $this->createNotFoundException('Unable to find Config entity.');
        }
        $templates=$this->template_list();
        $descriptions=array();
        foreach($templates as $tpl){
            $desc=__DIR__.'/../../Resources/views/'.$tpl.'/description.txt';
            if(file_exists($desc)){
                $descriptions[$tpl]=file_get_contents($desc);
            }
            else{
                $descriptions[$tpl]='';
            }
        }
        $editForm=$this->createForm(new ConfigTemplateType(),$config,array('templates'=>$templates));
        return $this->render('PlantnetDataBundle:Backend\Config:config_edit_template.html.twig',array(
            'entity'=>$config,
            'views'=>$templates,
            'descriptions'=>$descriptions,
            'edit_form'=>$editForm->createView(),
            'current'=>'config'
        ));
    }

    /**
     * Edits Config entity.
     *
     * @Route("/update_template", name="config_update_template")
     * @Method("post")
     * @Template()
     */
    public function config_update_templateAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $this->createNotFoundException('Unable to find Config entity.');
        }
        $templates=$this->template_list();
        $editForm=$this->createForm(new ConfigTemplateType(),$config,array('templates'=>$templates));
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $editForm->bind($request);
            $dm->persist($config);
            $dm->flush();
            $this->config_update_templates($config->getTemplate());
            $this->get('session')->getFlashBag()->add('msg_success','Template updated');
        }
        return $this->render('PlantnetDataBundle:Backend\Config:config_edit_template.html.twig',array(
            'entity'=>$config,
            'views'=>$templates,
            'edit_form'=>$editForm->createView(),
            'current'=>'config'
        ));
    }

    private function config_update_templates($tpl)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase());
        $database=$dm->getRepository('PlantnetDataBundle:Database')
            ->findOneBy(array(
                'name'=>$user->getDbName()
            ));
        if(!$database){
            throw $this->createNotFoundException('Unable to find Database entity.');
        }
        $parent=$database->getParent();
        if($parent){
            $database=$parent;
        }
        $dbs=array();
        $dbs[]=$database->getName();
        $children=$database->getChildren();
        if(count($children)){
            foreach($children as $child){
                $dbs[]=$child->getName();
            }
        }
        foreach($dbs as $db){
            $connection=new \MongoClient();
            $db=$connection->$db;
            $db->Config->update(array(),array('$set'=>array('template'=>$tpl)));
        }
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
    }
}