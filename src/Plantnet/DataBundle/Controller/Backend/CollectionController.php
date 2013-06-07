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

use Plantnet\DataBundle\Utils\StringSearch;

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
        $collections=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
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
        $document=new Collection();
        $request=$this->getRequest();
        $form=$this->createForm(new CollectionType(),$document);
        if('POST'===$request->getMethod()){
            $form->bindRequest($request);
            $url=$document->getUrl();
            if(StringSearch::isGoodForUrl($url)){
                $document->setAlias($user->getUsername().'_'.$url);
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
        $collection = $dm->getRepository('PlantnetDataBundle:Collection')
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
            $editForm->bindRequest($request);
            $url=$collection->getUrl();
            if(StringSearch::isGoodForUrl($url)){
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

    /**
     * Deletes a Collection entity.
     *
     * @Route("/{id}/delete", name="collection_delete")
     * @Method("post")
     */
    public function collection_deleteAction($id)
    {
        $form = $this->createDeleteForm($id);
        $request = $this->getRequest();
        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);
            if ($form->isValid()) {
                $user=$this->container->get('security.context')->getToken()->getUser();
                // $dm=$this->get('doctrine.odm.mongodb.document_manager');
                // $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                // $collection = $dm->getRepository('PlantnetDataBundle:Collection')
                //     ->findOneBy(array(
                //         'id'=>$id
                //     ));
                // if(!$collection){
                //     throw $this->createNotFoundException('Unable to find Collection entity.');
                // }
                // /*
                // * Remove Modules
                // */
                // $modules=$collection->getModules();
                // if(count($modules))
                // {
                //     foreach($modules as $module)
                //     {
                //         $this->forward('PlantnetDataBundle:Backend\Modules:module_delete',array(
                //             'id'=>$module->getId()
                //         ));
                //     }
                // }
                // /*
                // * Remove csv directory (and files)
                // */
                // $dir=__DIR__.'/../../Resources/uploads/'.$collection->getAlias();
                // if(file_exists($dir)&&is_dir($dir))
                // {
                //     $files=scandir($dir);
                //     foreach($files as $file)
                //     {
                //         if($file!='.'&&$file!='..')
                //         {
                //             unlink($dir.'/'.$file);
                //         }
                //     }
                //     rmdir($dir);
                // }
                // $db=$this->getDataBase($user);
                // $m=new \MongoClient();
                // $m->$db->Collection->remove(
                //     array('_id'=>new \MongoId($collection->getId()))
                // );
                $kernel=$this->get('kernel');
                $command=$this->get_php_path().' '.$kernel->getRootDir().'/console publish:delete collection '.$id.' '.$user->getDbName().' &> /dev/null &';
                $process=new \Symfony\Component\Process\Process($command);
                $process->start();
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

    private function get_php_path()
    {
        if(isset($_SERVER['HOSTNAME'])&&$_SERVER['HOSTNAME']=='bourgeais.cirad.fr'){
            return '/opt/php/bin/php';
        }
        if(isset($_SERVER['HTTP_HOST'])&&substr_count($_SERVER['HTTP_HOST'],'publish.plantnet-project.org')){
            return '/opt/php/bin/php';
        }
        return 'php';
    }
}
