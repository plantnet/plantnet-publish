<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Plantnet\DataBundle\Document\Collection,
    Plantnet\DataBundle\Form\Type\CollectionType;

use Symfony\Component\Form\FormError;

use Plantnet\DataBundle\Utils\StringHelp;

/**
 * Collection controller.
 *
 * @Route("/admin/collection")
 */
class CollectionController extends Controller
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

    public function collection_listAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collections=$dm->createQueryBuilder('PlantnetDataBundle:Collection')
            ->sort('name','asc')
            ->getQuery()
            ->execute();
        return $this->render('PlantnetDataBundle:Backend\Collection:collection_list.html.twig',array(
            'collections'=>$collections,
            'current'=>'administration'
        ));
    }

    /**
     * Displays a form to create a new Collection entity.
     *
     * @Route("/collection/new", name="collection_new")
     * @Template()
     */
    public function collection_newAction()
    {
        $document=new Collection();
        $form=$this->createForm(new CollectionType(),$document);
        return $this->render('PlantnetDataBundle:Backend\Collection:collection_new.html.twig',array(
            'entity'=>$document,
            'form'=>$form->createView()
        ));
    }

    /**
     * Creates a new Collection entity.
     *
     * @Route("/collection/create", name="collection_create")
     * @Method("post")
     * @Template()
     */
    public function collection_createAction()
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
        $document=new Collection();
        $request=$this->getRequest();
        $form=$this->createForm(new CollectionType(),$document);
        if('POST'===$request->getMethod()){
            $form->bind($request);
            $url=$document->getUrl();
            if(StringHelp::isGoodForUrl($url)){
                $document->setAlias(StringHelp::cleanToPath($config->getOriginaldb().'_'.$url));
                $nb_alias=$dm->createQueryBuilder('PlantnetDataBundle:Collection')
                    ->field('alias')->equals($document->getAlias())
                    ->hydrate(true)
                    ->getQuery()
                    ->execute()
                    ->count();
                if($nb_alias==0){
                    if($form->isValid()){
                        $document->setDeleting(false);
                        $dm->persist($document);
                        $dm->flush();
                        return $this->redirect($this->generateUrl('module_new',array(
                            'id'=>$document->getId(),
                            'type'=>'module'
                        )));
                    }
                }
                else{
                    $form->get('url')->addError(new FormError('This value is already used by system (URL or file path).'));
                }
            }
            else{
                $form->get('url')->addError(new FormError('Illegal characters (allowed \'a-z\', \'0-9\', \'-\', \'_\').'));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Collection:collection_new.html.twig',array(
            'entity'=>$document,
            'form'=>$form->createView()
        ));
    }

    /**
     * Displays a form to edit an existing Collection entity.
     *
     * @Route("/{id}/edit", name="collection_edit")
     * @Template()
     */
    public function collection_editAction($id)
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
        $editForm=$this->createForm(new CollectionType(),$collection);
        $deleteForm=$this->createDeleteForm($id);
        return $this->render('PlantnetDataBundle:Backend\Collection:collection_edit.html.twig',array(
            'entity'=>$collection,
            'edit_form'=>$editForm->createView(),
            'delete_form'=>$deleteForm->createView(),
        ));
    }

    /**
     * Edits an existing Collection entity.
     *
     * @Route("/{id}/update", name="collection_update")
     * @Method("post")
     * @Template()
     */
    public function collection_updateAction($id)
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
        $editForm=$this->createForm(new CollectionType(),$collection);
        $deleteForm=$this->createDeleteForm($id);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $editForm->bind($request);
            $url=$collection->getUrl();
            if(StringHelp::isGoodForUrl($url)){
                if($editForm->isValid()){
                    $dm->persist($collection);
                    $dm->flush();
                    return $this->redirect($this->generateUrl('collection_edit',array('id'=>$id)));
                }
            }
            else{
                $form->get('url')->addError(new FormError('Illegal characters (allowed \'a-z\', \'0-9\', \'-\', \'_\').'));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Collection:collection_edit.html.twig',array(
            'entity'=>$collection,
            'edit_form'=>$editForm->createView(),
            'delete_form'=>$deleteForm->createView(),
        ));
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id'=>$id))
            ->add('id','hidden')
            ->getForm();
    }

    /**
     * Deletes a Collection entity.
     *
     * @Route("/{id}/delete", name="collection_delete")
     * @Method("post")
     */
    public function collection_deleteAction($id)
    {
        $form=$this->createDeleteForm($id);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $user=$this->container->get('security.context')->getToken()->getUser();
                $kernel=$this->get('kernel');
                $command=$this->container->getParameter('php_bin').' '.$kernel->getRootDir().'/console publish:delete collection '.$id.' '.$user->getDbName().' &> /dev/null &';
                $process=new \Symfony\Component\Process\Process($command);
                $process->start();
            }
        }
        return $this->redirect($this->generateUrl('admin_index'));
    }
}
