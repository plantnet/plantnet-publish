<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Plantnet\DataBundle\Document\Config,
    Plantnet\DataBundle\Form\Type\ConfigType;

/**
 * Config controller.
 *
 * @Route("/admin/config", options={"i18n" = false})
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
            $config=new Config();
            $config->setDefaultlanguage($this->container->getParameter('locale'));
            $config->setAvailablelanguages(array('0'=>$this->container->getParameter('locale')));
            $config->setCustomlanguages(array());
            $dm->persist($config);
            $dm->flush();
        }
        $editForm=$this->createForm(new ConfigType(),$config,array('languages'=>$this->container->getParameter('locales')));
        return $this->render('PlantnetDataBundle:Backend\Config:config_edit.html.twig',array(
            'entity'=>$config,
            'edit_form'=>$editForm->createView()
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
        $editForm=$this->createForm(new ConfigType(),$config,array('languages'=>$this->container->getParameter('locales')));
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $editForm->bind($request);
            $default=$config->getDefaultlanguage();
            $availables=$config->getAvailablelanguages();
            if(!in_array($default,$availables)){
                $availables[]=$default;
                $config->setAvailablelanguages($availables);
            }
            if($editForm->isValid()){
                $dm->persist($config);
                $dm->flush();
                return $this->redirect($this->generateUrl('config_edit'));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Config:config_edit.html.twig',array(
            'entity'=>$config,
            'edit_form'=>$editForm->createView()
        ));
    }
}
