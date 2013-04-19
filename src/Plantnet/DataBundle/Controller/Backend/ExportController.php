<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * Export controller.
 *
 * @Route("/export")
 */
class ExportController extends Controller
{
    private $dir_name='';
    private $imgs_dir_name='images';
    private $thumbs_dir_name='thumbnails';

    private function getDataBase($user=null,$dm=null)
    {
        if($user)
        {
            return $user->getDbName();
        }
        elseif($dm)
        {
            return $dm->getConfiguration()->getDefaultDB();
        }
        return $this->container->getParameter('mdb_base');
    }

    /**
     * @Route("/collection/{collection}/module/{module}", name="admin_module_export_idao")
     * @Template()
     */
    public function module_export_idaoAction($collection,$module)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'name'=>$collection
            ));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'name'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        if($module->getType()!='text'){
            throw $this->createNotFoundException('Unable to load data.');
        }
        $this->dir_name='./uploads/_tmp';
        $this->dir_name.=str_replace('.','',microtime(true));
        mkdir($this->dir_name);
        mkdir($this->dir_name.'/'.$this->imgs_dir_name);
        mkdir($this->dir_name.'/'.$this->thumbs_dir_name);
        $this->load_plantunits($dm,$module);
        if(file_exists($this->dir_name)&&is_dir($this->dir_name))
        {
            $this->remove_directory($this->dir_name);
        }
        echo 'ok';
        exit;
        return $this->render('PlantnetDataBundle:Backend:index.html.twig',array(
            'current'=>'administration'
        ));
    }

    private function load_plantunits($dm,$module)
    {
        $plantunits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->field('module')->references($module)
            ->hydrate(false)
            ->select('id')
            ->getQuery()
            ->execute();
        foreach($plantunits as $plantunit)
        {
            $this->load_plantunit_data($dm,$module,$plantunit['_id']);
        }
    }

    private function load_plantunit_data($dm,$module,$plantunit_id)
    {
        $plantunit=$dm->getRepository('PlantnetDataBundle:Plantunit')
            ->findOneBy(array(
                'module.id'=>$module->getId(),
                'id'=>$plantunit_id
            ));
        $display=array();
        $fields=$module->getProperties();
        foreach($fields as $row){
            if($row->getDetails()==true){
                $display[]=$row->getId();
            }
        }
        $others=$plantunit->getOthers();
        $tab_others_groups=array();
        if(count($others))
        {
            foreach($others as $other)
            {
                if(!in_array($other->getModule()->getId(),array_keys($tab_others_groups)))
                {
                    $tab_others_groups[$other->getModule()->getId()]=array(
                        $other->getModule(),
                        array()
                    );
                }
                $tab_others_groups[$other->getModule()->getId()][1][]=$other;
            }
        }
        $template=$this->container->get('twig')->loadTemplate('PlantnetDataBundle:Backend\Export_IDAO:page.html.twig');
        $page=$template->render(array(
            'module'=>$module,
            'plantunit'=>$plantunit,
            'display'=>$display,
            'tab_others_groups'=>$tab_others_groups,
            'imgs_dir_name'=>$this->imgs_dir_name,
            'thumbs_dir_name'=>$this->thumbs_dir_name,
        ));
        $new_page=fopen($this->dir_name.'/'.$plantunit->getIdentifier().'.html','w');
        if($new_page)
        {
            fwrite($new_page,$page);
            fclose($new_page);
        }
        $images=$plantunit->getImages();
        if(count($images))
        {
            foreach($images as $image)
            {
                $img_name=$image->getPath();
                $img_source_dir='./uploads/'.$image->getModule()->getUploaddir();
                $thumb_size='thumb_180_120';
                $thumb_source_dir='./media/cache/'.$thumb_size.'/uploads/'.$image->getModule()->getUploaddir();
                if(file_exists($img_source_dir.'/'.$img_name))
                {
                    $this->container->get('liip_imagine.controller')->filterAction($this->getRequest(),$img_source_dir.'/'.$img_name,$thumb_size);
                    copy($img_source_dir.'/'.$img_name,$this->dir_name.'/'.$this->imgs_dir_name.'/'.$img_name);
                }
                if(file_exists($thumb_source_dir.'/'.$img_name))
                {
                    copy($thumb_source_dir.'/'.$img_name,$this->dir_name.'/'.$this->thumbs_dir_name.'/'.$img_name);
                }
            }
        }
        $zip=$this->folder_to_zip($this->dir_name.'/','./Publish_to_IDAO.zip');
        if($zip)
        {
            header('Content-Transfer-Encoding: binary');
            header('Content-Disposition: attachment; filename="Publish_to_IDAO.zip"');
            header('Content-Length: '.filesize('./Publish_to_IDAO.zip'));
            readfile('./Publish_to_IDAO.zip');
        }
    }

    private function remove_directory($dir_name)
    {
        $files=array_diff(scandir($dir_name),array('.','..'));
        foreach($files as $file)
        {
            if(is_dir($dir_name.'/'.$file))
            {
                $this->remove_directory($dir_name.'/'.$file);
            }
            else
            {
                unlink($dir_name.'/'.$file);
            }
        }
        rmdir($dir_name);
    }

    function folder_to_zip($source,$destination)
    {
        if(!extension_loaded('zip')||!file_exists($source))
        {
            return false;
        }
        $zip=new \ZipArchive();
        if(!$zip->open($destination,\ZIPARCHIVE::CREATE))
        {
            return false;
        }
        $source=str_replace('\\','/',realpath($source));
        if(is_dir($source)===true)
        {
            $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source),\RecursiveIteratorIterator::SELF_FIRST);
            foreach($files as $file)
            {
                $file=str_replace('\\','/',$file);
                if(in_array(substr($file,strrpos($file,'/')+1),array('.','..')))
                    continue;
                $file=realpath($file);
                if(is_dir($file)===true)
                {
                    $zip->addEmptyDir(str_replace($source.'/','',$file.'/'));
                }
                elseif(is_file($file) === true)
                {
                    $zip->addFromString(str_replace($source.'/','',$file),file_get_contents($file));
                }
            }
        }
        elseif(is_file($source)===true)
        {
            $zip->addFromString(basename($source),file_get_contents($source));
        }
        $zip->close();
        return $zip;
    }
}
