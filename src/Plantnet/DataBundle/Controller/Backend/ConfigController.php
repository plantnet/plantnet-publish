<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Plantnet\DataBundle\Document\Config,
    Plantnet\DataBundle\Form\Type\ConfigType,
    Plantnet\DataBundle\Form\Type\ConfigImageType;

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
                    $this->check_databases($availables,$user);
                }
                $config->setAvailablelanguages($availables);
            }
            $dm->persist($config);
            $dm->flush();
        }
        return $this->render('PlantnetDataBundle:Backend\Config:config_edit.html.twig',array(
            'entity'=>$config,
            'edit_form'=>$editForm->createView(),
            'current'=>'config'
        ));
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
                }
            }
        }
        return $this->redirect($this->generateUrl('config_edit_banner'));
    }

    private function check_databases($languages,$user)
    {
        $prefix=$this->container->getParameter('mdb_base').'_';
        $default_db=$user->getDbName();
        $connection=new \MongoClient();
        $dbs=$connection->admin->command(array(
            'listDatabases'=>1
        ));
        $dbs_array=array();
        foreach($dbs['databases'] as $db){
            $db_name=$db['name'];
            if(substr_count($db_name,$prefix)&&substr_count($db_name,$default_db)){
                $dbs_array[]=$db_name;
            }
        }
        $news=array();
        foreach($languages as $language){
            if(!in_array($default_db.'_'.$language,$dbs_array)){
                $news[]=$default_db.'_'.$language;
            }
        }
        if(count($news)){
            foreach($news as $new){
                $db=$connection->$new;
                $db->listCollections();
                //collections
                $db->createCollection('Collection');
                $db->createCollection('Config');
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
                $db->Page->insert(array('name'=>'home','alias'=>'home','order'=>1));
                $db->Page->insert(array('name'=>'mentions','alias'=>'mentions','order'=>2));
                $db->Page->insert(array('name'=>'credits','alias'=>'credits','order'=>3));
                $db->Page->insert(array('name'=>'contacts','alias'=>'contacts','order'=>4));
                //init config
                $db->Config->insert(array('defaultlanguage'=>'_'));
            }
        }
    }
}
