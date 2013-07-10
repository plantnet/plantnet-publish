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
        return $this->render('PlantnetDataBundle:Backend\Config:config_edit_banner.html.twig',array(
            'entity'=>$config,
            'edit_form'=>$editForm->createView(),
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
        }
        $editForm=$this->createForm(new ConfigImageType(),$config);
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
            'current'=>'config'
        ));
    }
}
