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
	* @Route("/{name}/file_manager", requirements={"name" = "\w+"}, name="file_manager")
	* @Template()
	*/
    public function indexAction($name)
    {
        $zipData=new ZipData();
        $form=$this->createForm(new ZipForm(),$zipData);
        return array(
            'form'=>$form->createView(),
            'name'=>$name
        );
    }
    /**
    * @Route("/{name}/file_upload/", requirements={"name" = "\w+"}, name="file_upload")
    * @Template()
    */
    public function uploadAction($name,Request $request)
    {
        $zipData=new ZipData();
        $form=$this->createForm(new ZipForm(),$zipData);
        if($request->getMethod()=='POST')
        {
            $form->bindRequest($request);
            if($form->isValid())
            {
                $dir=$this->get('kernel')->getRootDir().'/../web/uploads/'.$name.'/';
                $data='data.zip';
                $form->get('zipFile')->getData()->move($dir,$data);
                $zipData->zipPath=$dir.$data;
                $zipData->extractTo($dir);
            }
        }
        return array(
            'form'=>$form->createView(),
            'name'=>$name
        );
    }
}