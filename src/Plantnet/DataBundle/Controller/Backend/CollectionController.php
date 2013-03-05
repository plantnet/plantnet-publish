<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Plantnet\DataBundle\Document\Collection,
    Plantnet\DataBundle\Form\Type\CollectionType;

/**
 * Collection controller.
 *
 */
class CollectionController extends Controller
{
    /**
     * Displays a form to edit an existing Collection entity.
     *
     * @Route("/coll/new", name="collectionlist")
     * @Template()
     */
    public function collectionlistAction()
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $collections = $dm->getRepository('PlantnetDataBundle:Collection')
            ->findAll();
        $modules = array();
        foreach($collections as $collection){
            $coll = array(
                'collection'=>$collection->getName(),
                'id'=>$collection->getId(),
                'owner'=>$collection->getUser()->getUsernameCanonical()
            );
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->findBy(array('collection.id' => $collection->getId()));
            array_push($coll, $module);
            array_push($modules, $coll);
        }
        return $this->render('PlantnetDataBundle:Backend\Collection:collectionlist.html.twig',array('collections' => $collections, 'list' => $modules, 'current' => 'administration'));
    }

     /**
     * Displays a form to edit an existing Collection entity.
     *
     * @Route("/{id}/edit", name="collection_edit")
     * @Template()
     */
    public function editAction($id)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $entity = $dm->getRepository('PlantnetDataBundle:Collection')->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $editForm = $this->createForm(new CollectionType(), $entity);
        $deleteForm = $this->createDeleteForm($id);
        return array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Edits an existing Collection entity.
     *
     * @Route("/{id}/update", name="collection_update")
     * @Method("post")
     * @Template("PlantnetBotaBundle:Backend\Collection:edit.html.twig")
     */
    public function updateAction($id)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');
        $entity = $dm->getRepository('PlantnetDataBundle:Collection')->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $editForm   = $this->createForm(new CollectionType(), $entity);
        $deleteForm = $this->createDeleteForm($id);
        $request = $this->getRequest();
        if ('POST' === $request->getMethod()) {
            $editForm->bindRequest($request);
            if ($editForm->isValid()) {
                // $em = $this->getDoctrine()->getEntityManager();
                $dm->persist($entity);
                $dm->flush();
                return $this->redirect($this->generateUrl('collection_edit', array('id' => $id)));
            }
        }
        return array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Deletes a Collection entity.
     *
     * @Route("/{id}/delete", name="collection_delete")
     * @Method("post")
     */
    public function deleteAction($id)
    {
        $form = $this->createDeleteForm($id);
        $request = $this->getRequest();
        if ('POST' === $request->getMethod()) {
            $form->bindRequest($request);
            if ($form->isValid()) {
                $dm = $this->get('doctrine.odm.mongodb.document_manager');
                $collection = $dm->getRepository('PlantnetDataBundle:Collection')->find($id);
                if(!$collection){
                    throw $this->createNotFoundException('Unable to find Collection entity.');
                }
                /*
                * Remove csv directory (and files)
                */
                $dir=__DIR__.'/../../Resources/uploads/'.$collection->getAlias();
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
                $dm->remove($collection);
                $dm->flush();
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
