<?php

namespace Plantnet\FileManagerBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Plantnet\FileManagerBundle\Entity\ZipData;
use Plantnet\FileManagerBundle\Form\ZipForm;

class DefaultController extends Controller
{
    /**
	* @Template()
	*/
    public function indexAction($name)
    {
        $zipData=new ZipData();
        $form=$this->createForm(new ZipForm(),$zipData);
        return $this->render('PlantnetFileManagerBundle:Default:index.html.twig',array(
            'form'=>$form->createView(),
            'name'=>$name
        ));
    }
    /**
    * @Template()
    */
    public function uploadAction($name,Request $request)
    {
        $zipData=new ZipData();
        $form=$this->createForm(new ZipForm(),$zipData);
        if($request->getMethod()=='POST'){
            $form->bind($request);
            if($form->isValid()){
                $dir=$this->get('kernel')->getRootDir().'/../web/uploads/'.$name.'/';
                $data='data.zip';
                $form->get('zipFile')->getData()->move($dir,$data);
                $zipData->zipPath=$dir.$data;
                $zipData->extractTo($dir);
            }
        }
        return $this->render('PlantnetFileManagerBundle:Default:upload.html.twig',array(
            'form'=>$form->createView(),
            'name'=>$name
        ));
    }
}