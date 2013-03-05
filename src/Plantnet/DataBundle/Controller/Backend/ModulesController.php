<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\RedirectResponse;

use
    Plantnet\DataBundle\Document\Module,
    Plantnet\DataBundle\Form\ImportFormType,
    Plantnet\DataBundle\Form\Type\ModulesType
    ;

/**
 * Module controller.
 *
 * @Route("/admin/module")
 */
class ModulesController extends Controller
{


    /**
     * @Route("/newmod", name="new_mod")
     * @Template()
     */
    public function newmodAction()
    {
        $entity = new Modules();
        $form   = $this->createForm(new ModulesType(), $entity);
        
        $properties = '';

        
        //$form = $this->container->get('form.factory')->create(new ModulesType());

	        return $this->container->get('templating')->renderResponse('PlantnetDataBundle:Backend\Modules:newmod.html.twig', array(
		        'entity' => $entity,
                'properties' => $properties,
		        'form' => $form->createView()
	    ));
    }



    /**
     * Displays a form to edit an existing Module entity.
     *
     * @Route("/{id}/edit", name="modules_edit")
     * @Template()
     */
    public function editAction($id)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        $entity = $dm->getRepository('PlantnetDataBundle:Module')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        

        $editForm = $this->get('form.factory')->create(new ModulesType(), $entity);
        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Edits an existing Module entity.
     *
     * @Route("/{id}/update", name="modules_update")
     * @Method("post")
     * @Template("PlantnetBotaBundle:Backend\Modules:edit.html.twig")
     */
    public function updateAction($id)
    {
        $dm = $this->get('doctrine.odm.mongodb.document_manager');

        $entity = $dm->getRepository('PlantnetDataBundle:Module')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Module entity.');
        }

        $editForm   = $this->createForm(new ModulesType(), $entity);
        $deleteForm = $this->createDeleteForm($id);

        $request = $this->getRequest();

        if ('POST' === $request->getMethod()) {
            $editForm->bindRequest($request);

            if ($editForm->isValid()) {
                $dm = $this->get('doctrine.odm.mongodb.document_manager');
                $dm->persist($entity);

                $dm->flush();

                return $this->redirect($this->generateUrl('modules_edit', array('id' => $id)));
            }
        }

        return array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Deletes a Module entity.
     *
     * @Route("/{id}/delete", name="modules_delete")
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
                $module = $dm->getRepository('PlantnetDataBundle:Module')->find($id);
                if (!$module) {
                    throw $this->createNotFoundException('Unable to find Module entity.');
                }
                $collections=$dm->getRepository('PlantnetDataBundle:Collection')->findBy(array('modules.id'=>$id));
                $collection=null;
                foreach($collections as $c)
                {
                    $collection=$c;
                }
                $collection->getModules()->removeElement($module);

                /*
                * Remove csv file
                */
                $csvfile=__DIR__.'/../../Resources/uploads/'.$collection->getAlias().'/'.$module->getName_fname().'.csv';
                if(file_exists($csvfile))
                {
                    unlink($csvfile);
                }

                /*
                * Delete children (Modules)
                */

                /*
                * Delete Plantunits
                */

                /*
                * Delete Locations
                */

                $dm->persist($collection);
                $dm->remove($module);
                $dm->flush();
            }
        }

        return $this->redirect($this->generateUrl('admin_index'));
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm()
        ;
    }
}
