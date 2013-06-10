<?php

namespace Plantnet\DataBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

use Plantnet\DataBundle\Document\Module,
    Plantnet\DataBundle\Document\Plantunit,
    Plantnet\DataBundle\Document\Property,
    Plantnet\DataBundle\Document\Image,
    Plantnet\DataBundle\Document\Location,
    Plantnet\DataBundle\Document\Coordinates,
    Plantnet\DataBundle\Document\Other;

ini_set('memory_limit','-1');

class DeleteCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('publish:delete')
            ->setDescription('delete collections or modules entities')
            ->addArgument('type',InputArgument::REQUIRED,'Specify the type of the entity')
            ->addArgument('id',InputArgument::REQUIRED,'Specify the ID of the entity')
            ->addArgument('dbname',InputArgument::REQUIRED,'Specify a database name')
        ;
    }

    protected function execute(InputInterface $input,OutputInterface $output)
    {
        $type=$input->getArgument('type');
        $id=$input->getArgument('id');
        $dbname=$input->getArgument('dbname');
        if($type&&$id&&($type=='collection'||$type=='module')&&$dbname){
            if($type=='module'){
                $this->delete_module($dbname,$id);
            }
            elseif($type=='collection'){
                $this->delete_collection($dbname,$id);
            }
        }
    }

    private function delete_module($dbname,$id)
    {
        $error='';
        $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($dbname);
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($id);
        if(!$module){
            $error='Unable to find Module entity.';
        }
        $collection=$module->getCollection();
        if(!$collection){
            $error='Unable to find Collection entity.';
        }
        $module->setDeleting(true);
        $dm->persist($module);
        $dm->flush();
        $children=$module->getChildren();
        if(count($children)){
            foreach($children as $child){
                $this->delete_module($dbname,$child->getId());
            }
        }
        /*
        * Remove csv file
        */
        $csvfile=__DIR__.'/../Resources/uploads/'.$collection->getAlias().'/'.$module->getAlias().'.csv';
        if(file_exists($csvfile)){
            unlink($csvfile);
        }
        /*
        * Remove upload directory
        */
        $dir=$module->getUploaddir();
        if($dir){
            $dir=__DIR__.'/../../../../web/uploads/'.$dir;
            if(file_exists($dir)&&is_dir($dir)){
                $files=scandir($dir);
                foreach($files as $file){
                    if($file!='.'&&$file!='..'){
                        unlink($dir.'/'.$file);
                    }
                }
                rmdir($dir);
            }
        }
        /*
        * Check for punits update
        */
        $parent_to_update=null;
        if($module->getType()=='image'||$module->getType()=='locality'){
            if($module->getParent()&&$module->getParent()->getDeleting()===false){
                $parent_to_update=$module->getParent()->getId();
            }
        }
        /*
        * Remove Module + Cascade
        */
        if($module->getType()=='image'){
            $dm->createQueryBuilder('PlantnetDataBundle:Image')
                ->remove()
                ->field('module')->references($module)
                ->getQuery()
                ->execute();
        }
        elseif($module->getType()=='locality'){
            $dm->createQueryBuilder('PlantnetDataBundle:Location')
                ->remove()
                ->field('module')->references($module)
                ->getQuery()
                ->execute();
        }
        elseif($module->getType()=='other'){
            $dm->createQueryBuilder('PlantnetDataBundle:Other')
                ->remove()
                ->field('module')->references($module)
                ->getQuery()
                ->execute();
        }
        elseif($module->getType()=='text'){
            $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                ->remove()
                ->field('module')->references($module)
                ->getQuery()
                ->execute();
            $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->remove()
                ->field('module')->references($module)
                ->getQuery()
                ->execute();
        }
        $dm->remove($module);
        $dm->flush();
        /*
        * Check for punits update
        */
        if($parent_to_update){
            $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
            //images
            $ids_img=array();
            $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->hydrate(false)
                ->select('_id')
                ->field('module')->references($module_parent)
                ->field('hasimages')->equals(true)
                ->getQuery()
                ->execute();
            foreach($punits as $id){
                $ids_img[]=$id['_id']->{'$id'};
            }
            unset($punits);
            foreach($ids_img as $id){
                $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                    ->findOneBy(array(
                        'id'=>$id
                    ));
                $images=$punit->getImages();
                if(!count($images)){
                    $punit->setHasimages(false);
                    $dm->persist($punit);
                    $dm->flush();
                }
            }
            $dm->clear();
            $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
            //locations
            $ids_loc=array();
            $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->hydrate(false)
                ->select('_id')
                ->field('module')->references($module_parent)
                ->field('haslocations')->equals(true)
                ->getQuery()
                ->execute();
            foreach($punits as $id){
                $ids_loc[]=$id['_id']->{'$id'};
            }
            unset($punits);
            foreach($ids_loc as $id){
                $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                    ->findOneBy(array(
                        'id'=>$id
                    ));
                $locations=$punit->getLocations();
                if(!count($locations)){
                    $punit->setHaslocations(false);
                    $dm->persist($punit);
                    $dm->flush();
                }
            }
            $dm->clear();
            $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
            //taxons
            $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                ->update()
                ->multiple(true)
                ->field('module')->references($module_parent)
                ->field('hasimages')->set(false)
                ->field('haslocations')->set(false)
                ->getQuery()
                ->execute();
            $ids_tax=array();
            $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                ->hydrate(false)
                ->select('_id')
                ->field('module')->references($module_parent)
                ->field('taxon')->notEqual(null)
                ->getQuery()
                ->execute();
            foreach($punits as $id){
                $ids_tax[]=$id['_id']->{'$id'};
            }
            unset($punits);
            foreach($ids_tax as $id){
                $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                    ->findOneBy(array(
                        'id'=>$id
                    ));
                $img_bool=$punit->getHasimages();
                $loc_bool=$punit->getHaslocations();
                $taxon=$punit->getTaxon();
                if($taxon&&($img_bool||$loc_bool)){
                    if($img_bool){
                        $taxon->setHasimages(true);
                    }
                    if($loc_bool){
                        $taxon->setHaslocations(true);
                    }
                    $dm->persist($taxon);
                    $parent_taxon=$taxon->getParent();
                    while($parent_taxon){
                        if($img_bool){
                            $parent_taxon->setHasimages(true);
                        }
                        if($loc_bool){
                            $parent_taxon->setHaslocations(true);
                        }
                        $dm->persist($parent_taxon);
                        $parent_taxon=$parent_taxon->getParent();
                    }
                }
                $dm->flush();
            }
            $dm->clear();
            $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
        }
    }

    private function delete_collection($dbname,$id)
    {
        $error='';
        $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($dbname);
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$collection){
            $error='Unable to find Collection entity.';
        }
        $collection->setDeleting(true);
        $dm->persist($collection);
        $dm->flush();
        /*
        * Remove Modules
        */
        $modules=$collection->getModules();
        if(count($modules)){
            foreach($modules as $module){
                $this->delete_module($dbname,$module->getId());
            }
        }
        /*
        * Remove csv directory (and files)
        */
        $dir=__DIR__.'/../Resources/uploads/'.$collection->getAlias();
        if(file_exists($dir)&&is_dir($dir)){
            $files=scandir($dir);
            foreach($files as $file){
                if($file!='.'&&$file!='..'){
                    unlink($dir.'/'.$file);
                }
            }
            rmdir($dir);
        }
        /*
        * Remove Collection + Cascade
        */
        $dm->remove($collection);
        $dm->flush();
    }
}