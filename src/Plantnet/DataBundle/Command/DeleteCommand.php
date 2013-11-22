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
        if($type&&$id&&($type=='collection'||$type=='module'||$type=='glossary')&&$dbname){
            if($type=='module'){
                $this->delete_module($dbname,$id);
            }
            elseif($type=='collection'){
                $this->delete_collection($dbname,$id);
            }
            elseif($type=='glossary'){
                $this->delete_glossary($dbname,$id);
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
        \MongoCursor::$timeout=-1;
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
        * Remove csv syn file
        */
        $csvsynfile=__DIR__.'/../Resources/uploads/'.$collection->getAlias().'/'.$module->getAlias().'_syn.csv';
        if(file_exists($csvsynfile)){
            unlink($csvsynfile);
        }
        /*
        * Remove csv desc file
        */
        $csvdescfile=__DIR__.'/../Resources/uploads/'.$collection->getAlias().'/'.$module->getAlias().'_desc.csv';
        if(file_exists($csvdescfile)){
            unlink($csvdescfile);
        }
        /*
        * Remove upload directory
        */
        $dir=$module->getUploaddir();
        $del_dir=true;
        $nb_equal_dirs=0;
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if($config){
            $original_db=$config->getOriginaldb();
            if($original_db){
                $connection=new \MongoClient();
                \MongoCursor::$timeout=-1;
                $dbs_array=array();
                $dbs=$connection->admin->command(array(
                    'listDatabases'=>1
                ));
                foreach($dbs['databases'] as $db){
                    $db_name=$db['name'];
                    if(substr_count($db_name,$original_db)){
                        $dbs_array[]=$db_name;
                    }
                }
                if(count($dbs_array)>1){
                    foreach($dbs_array as $chk_db){
                        $nb_equal_dirs+=$connection->$chk_db->Module->find(array('uploaddir'=>$dir))->count();
                    }
                }
            }
        }
        if($nb_equal_dirs>1){
            $del_dir=false;
        }
        if($dir&&$del_dir){
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
        //indexes
        $m=new \MongoClient();
        \MongoCursor::$timeout=-1;
        $old_indexes=$module->getIndexes();
        if(count($old_indexes)){
            foreach($old_indexes as $old){
                //delete old indexes
                $m->$dbname->command(array('deleteIndexes'=>'Plantunit','index'=>$old));
            }
        }
        $m=null;
        unset($m);
        $old_indexes=null;
        unset($old_indexes);
        $dm->remove($module);
        $dm->flush();
        $dm->detach($module);
        /*
        * Check for punits update
        */
        if($parent_to_update){
            $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
            if($module_parent){
                $has_img_child=false;
                $has_loc_child=false;
                $tmp_children=$module_parent->getChildren();
                if(count($tmp_children)){
                    foreach($tmp_children as $tmp_child){
                        if($tmp_child->getDeleting()===false){
                            if($tmp_child->getType()=='image'){
                                $has_img_child=true;
                            }
                            elseif($tmp_child->getType()=='locality'){
                                $has_loc_child=true;
                            }
                        }
                    }
                }
                //images
                \MongoCursor::$timeout=-1;
                if($has_img_child){
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
                    $punits=null;
                    unset($punits);
                    foreach($ids_img as $id){
                        $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                            ->findOneBy(array(
                                'id'=>$id
                            ));
                        if($punit){
                            $images=$punit->getImages();
                            if(!count($images)){
                                $punit->setHasimages(false);
                                $dm->persist($punit);
                                $dm->flush();
                            }
                            $images=null;
                            $dm->detach($punit);
                        }
                        $punit=null;
                    }
                    $dm->clear();
                    $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
                }
                else{
                    $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                        ->update()
                        ->multiple(true)
                        ->field('module')->references($module_parent)
                        ->field('hasimages')->equals(true)
                        ->field('hasimages')->set(false)
                        ->getQuery()
                        ->execute();
                }
                //locations
                \MongoCursor::$timeout=-1;
                if($has_loc_child){
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
                    $punits=null;
                    unset($punits);
                    foreach($ids_loc as $id){
                        $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                            ->findOneBy(array(
                                'id'=>$id
                            ));
                        if($punit){
                            $locations=$punit->getLocations();
                            if(!count($locations)){
                                $punit->setHaslocations(false);
                                $dm->persist($punit);
                                $dm->flush();
                            }
                            $locations=null;
                            $dm->detach($punit);
                        }
                        $punit=null;
                    }
                    $dm->clear();
                    $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
                }
                else{
                    $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                        ->update()
                        ->multiple(true)
                        ->field('module')->references($module_parent)
                        ->field('haslocations')->equals(true)
                        ->field('haslocations')->set(false)
                        ->getQuery()
                        ->execute();
                }
                //taxons
                \MongoCursor::$timeout=-1;
                if($has_img_child||$has_loc_child){
                    $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                        ->update()
                        ->multiple(true)
                        ->field('module')->references($module_parent)
                        ->field('hasimages')->set(false)
                        ->field('haslocations')->set(false)
                        ->getQuery()
                        ->execute();
                    $ids_tax=array();
                    if($has_img_child){
                        $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('module')->references($module_parent)
                            ->field('hasimages')->equals(true)
                            ->getQuery()
                            ->execute();
                        foreach($punits as $id){
                            $ids_tax[$id['_id']->{'$id'}]=$id['_id']->{'$id'};
                        }
                        $punits=null;
                    }
                    if($has_loc_child){
                        $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('module')->references($module_parent)
                            ->field('haslocations')->equals(true)
                            ->getQuery()
                            ->execute();
                        foreach($punits as $id){
                            $ids_tax[$id['_id']->{'$id'}]=$id['_id']->{'$id'};
                        }
                        $punits=null;
                    }
                    unset($punits);
                    foreach($ids_tax as $id){
                        $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                            ->findOneBy(array(
                                'id'=>$id
                            ));
                        if($punit){
                            $img_bool=$punit->getHasimages();
                            $loc_bool=$punit->getHaslocations();
                            $taxons=$punit->getTaxonsrefs();
                            if(count($taxons)&&($img_bool||$loc_bool)){
                                $need_flush=false;
                                foreach($taxons as $taxon){
                                    if($img_bool&&!$taxon->getHasimages()){
                                        $taxon->setHasimages(true);
                                        $need_flush=true;
                                    }
                                    if($loc_bool&&!$taxon->getHaslocations()){
                                        $taxon->setHaslocations(true);
                                        $need_flush=true;
                                    }
                                    if($need_flush){
                                        $dm->persist($taxon);
                                    }
                                    if($taxon->getIssynonym()){
                                        $taxon_valid=$taxon->getChosen();
                                        if($img_bool&&!$taxon_valid->getHasimages()){
                                            $taxon_valid->setHasimages(true);
                                            $need_flush=true;
                                        }
                                        if($loc_bool&&!$taxon_valid->getHaslocations()){
                                            $taxon_valid->setHaslocations(true);
                                            $need_flush=true;
                                        }
                                        if($need_flush){
                                            $dm->persist($taxon_valid);
                                        }
                                        $parent_taxon_valid=$taxon_valid->getParent();
                                        while($parent_taxon_valid){
                                            if($img_bool&&!$parent_taxon_valid->getHasimages()){
                                                $parent_taxon_valid->setHasimages(true);
                                                $need_flush=true;
                                            }
                                            if($loc_bool&&!$parent_taxon_valid->getHaslocations()){
                                                $parent_taxon_valid->setHaslocations(true);
                                                $need_flush=true;
                                            }
                                            if($need_flush){
                                                $dm->persist($parent_taxon_valid);
                                            }
                                            $parent_taxon_valid=$parent_taxon_valid->getParent();
                                        }
                                    }
                                }
                                if($need_flush){
                                    $dm->flush();
                                }
                                $dm->detach($punit);
                                $dm->clear();
                                $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
                            }
                            $punit=null;
                        }
                    }
                }
                else{
                    $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                        ->update()
                        ->multiple(true)
                        ->field('module')->references($module_parent)
                        ->field('hasimages')->set(false)
                        ->field('haslocations')->set(false)
                        ->getQuery()
                        ->execute();
                }
            }
        }
    }

    private function delete_collection($dbname,$id)
    {
        $error='';
        $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($dbname);
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
        \MongoCursor::$timeout=-1;
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
        * Remove Glossary
        */
        $glossary=$collection->getGlossary();
        if($glossary){
            $this->delete_glossary($dbname,$glossary->getId());
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
        $dm->detach($collection);
    }

    private function delete_glossary($dbname,$id)
    {
        $error='';
        $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($dbname);
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
        \MongoCursor::$timeout=-1;
        $glossary=$dm->getRepository('PlantnetDataBundle:Glossary')
            ->findOneBy(array(
                'id'=>$id
            ));
        if(!$glossary){
            $error='Unable to find Glossary entity.';
        }
        /*
        * Remove csv directory (and files)
        */
        $file=__DIR__.'/../Resources/uploads/'.$glossary->getCollection()->getAlias().'/glossary.csv';
        if(file_exists($file)){
            unlink($file);
        }
        $file=__DIR__.'/../Resources/uploads/'.$glossary->getCollection()->getAlias().'/glossary_syn.csv';
        if(file_exists($file)){
            unlink($file);
        }
        /*
        * Remove upload directory
        */
        $dir=$glossary->getUploaddir();
        $del_dir=true;
        $nb_equal_dirs=0;
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if($config){
            $original_db=$config->getOriginaldb();
            if($original_db){
                $connection=new \MongoClient();
                $dbs_array=array();
                $dbs=$connection->admin->command(array(
                    'listDatabases'=>1
                ));
                foreach($dbs['databases'] as $db){
                    $db_name=$db['name'];
                    if(substr_count($db_name,$original_db)){
                        $dbs_array[]=$db_name;
                    }
                }
                if(count($dbs_array)>1){
                    foreach($dbs_array as $chk_db){
                        $nb_equal_dirs+=$connection->$chk_db->Glossary->find(array('uploaddir'=>$dir))->count();
                    }
                }
            }
        }
        if($nb_equal_dirs>1){
            $del_dir=false;
        }
        if($dir&&$del_dir){
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
        * Remove Glossary + Cascade
        */
        $dm->remove($glossary);
        $dm->flush();
        $dm->detach($glossary);
    }
}