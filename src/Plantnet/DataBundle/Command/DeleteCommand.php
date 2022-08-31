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

    private function delete_punit($dm,$collection,$module,$dbname)
    {
        $children=$module->getChildren();
        if(count($children)){
            foreach($children as $child){
                $this->delete_module($dbname,$child->getId(),true);
            }
        }
        //
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
        //
        $this->drop_it($dm,$collection,$module,$dbname);
    }

    private function delete_image($dm,$collection,$module,$dbname,$fast=false)
    {
        $idmodule=$module->getId();
        $parent_to_update=null;
        if($module->getParent()&&$module->getParent()->getDeleting()===false){
            $parent_to_update=$module->getParent()->getId();
            if(!$fast){
                $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
                if($module_parent){
                    $has_img_child=false;
                    $img_child_ids=array();
                    $tmp_children=$module_parent->getChildren();
                    if(count($tmp_children)){
                        foreach($tmp_children as $tmp_child){
                            if($tmp_child->getDeleting()===false){
                                if($tmp_child->getType()=='image'&&$tmp_child->getId()!=$idmodule){
                                    $has_img_child=true;
                                    $img_child_ids[]=$tmp_child->getId();
                                }
                            }
                        }
                    }
                    //
                    \MongoCursor::$timeout=-1;
                    if(!$has_img_child){
                        $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->update()
                            ->multiple(true)
                            ->field('module')->references($module_parent)
                            ->field('hasimages')->equals(true)
                            ->field('hasimages')->set(false)
                            ->getQuery()
                            ->execute();
                    }
                    else{
                        //Punit with images
                        $ids_with_img=array();
                        $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('module')->references($module_parent)
                            ->field('hasimages')->equals(true)
                            ->getQuery()
                            ->execute();
                        foreach($punits as $id){
                            $ids_with_img[]=$id['_id']->{'$id'};
                        }
                        $punits=null;
                        unset($punits);
                        //Punit ids in images
                        $ids_in_img=array();
                        $punits=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                            ->hydrate(false)
                            ->select('plantunit')
                            ->field('module.id')->in($img_child_ids)
                            ->getQuery()
                            ->execute();
                        foreach($punits as $id){
                            $ids_in_img[]=$id['plantunit']['$id']->{'$id'};
                        }
                        $punits=null;
                        unset($punits);
                        // Punits without images
                        $punit_ids_without_images=array_diff($ids_with_img,$ids_in_img);
                        if(count($punit_ids_without_images)){
                            $tabs_to_update=array_chunk($punit_ids_without_images,100);
                            foreach($tabs_to_update as $sub_tab){
                                $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                                    ->update()
                                    ->multiple(true)
                                    ->field('module')->references($module_parent)
                                    ->field('id')->in($sub_tab)
                                    ->field('hasimages')->set(false)
                                    ->getQuery()
                                    ->execute();
                            }
                        }
                        $dm->clear();
                        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                        $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
                    }
                    //taxons
                    \MongoCursor::$timeout=-1;
                    if($has_img_child){
                        $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                            ->update()
                            ->multiple(true)
                            ->field('module')->references($module_parent)
                            ->field('hasimages')->set(false)
                            ->getQuery()
                            ->execute();
                        $ids_tax=array();
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
                        unset($punits);
                        foreach($ids_tax as $id){
                            $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                                ->findOneBy(array(
                                    'id'=>$id
                                ));
                            if($punit){
                                $img_bool=$punit->getHasimages();
                                $taxons=$punit->getTaxonsrefs();
                                if(count($taxons)&&($img_bool)){
                                    $need_flush=false;
                                    foreach($taxons as $taxon){
                                        if($img_bool&&!$taxon->getHasimages()){
                                            $taxon->setHasimages(true);
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
                                            if($need_flush){
                                                $dm->persist($taxon_valid);
                                            }
                                            $parent_taxon_valid=$taxon_valid->getParent();
                                            while($parent_taxon_valid){
                                                if($img_bool&&!$parent_taxon_valid->getHasimages()){
                                                    $parent_taxon_valid->setHasimages(true);
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
                                    $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
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
                            ->getQuery()
                            ->execute();
                    }
                }
            }
        }
        $dm->createQueryBuilder('PlantnetDataBundle:Image')
            ->remove()
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
        //
        $this->drop_it($dm,$collection,$module,$dbname);
    }

    private function delete_locality($dm,$collection,$module,$dbname,$fast=false)
    {
        $parent_to_update=null;
        if($module->getParent()&&$module->getParent()->getDeleting()===false){
            $parent_to_update=$module->getParent()->getId();
            if(!$fast){
                $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
                if($module_parent){
                    $has_loc_child=false;
                    $loc_child_ids=array();
                    $tmp_children=$module_parent->getChildren();
                    if(count($tmp_children)){
                        foreach($tmp_children as $tmp_child){
                            if($tmp_child->getDeleting()===false){
                                if($tmp_child->getType()=='locality'&&$tmp_child->getId()!=$idmodule){
                                    $has_loc_child=true;
                                    $loc_child_ids[]=$tmp_child->getId();
                                }
                            }
                        }
                    }
                    //
                    \MongoCursor::$timeout=-1;
                    if(!$has_loc_child){
                        $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->update()
                            ->multiple(true)
                            ->field('module')->references($module_parent)
                            ->field('haslocations')->equals(true)
                            ->field('haslocations')->set(false)
                            ->getQuery()
                            ->execute();
                    }
                    else{
                        //Punit with locations
                        $ids_with_loc=array();
                        $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                            ->hydrate(false)
                            ->select('_id')
                            ->field('module')->references($module_parent)
                            ->field('haslocations')->equals(true)
                            ->getQuery()
                            ->execute();
                        foreach($punits as $id){
                            $ids_with_loc[]=$id['_id']->{'$id'};
                        }
                        $punits=null;
                        unset($punits);
                        //Punit ids in locations
                        $ids_in_loc=array();
                        $punits=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                            ->hydrate(false)
                            ->select('plantunit')
                            ->field('module.id')->in($loc_child_ids)
                            ->getQuery()
                            ->execute();
                        foreach($punits as $id){
                            $ids_in_loc[]=$id['plantunit']['$id']->{'$id'};
                        }
                        $punits=null;
                        unset($punits);
                        //Punits without locations
                        $punit_ids_without_locations=array_diff($ids_with_loc,$ids_in_loc);
                        if(count($punit_ids_without_locations)){
                            $tabs_to_update=array_chunk($punit_ids_without_locations,100);
                            foreach($tabs_to_update as $sub_tab){
                                $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                                    ->update()
                                    ->multiple(true)
                                    ->field('module')->references($module_parent)
                                    ->field('id')->in($sub_tab)
                                    ->field('haslocations')->set(false)
                                    ->getQuery()
                                    ->execute();
                            }
                        }
                        $dm->clear();
                        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                        $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
                    }
                    //taxons
                    \MongoCursor::$timeout=-1;
                    if($has_loc_child){
                        $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                            ->update()
                            ->multiple(true)
                            ->field('module')->references($module_parent)
                            ->field('haslocations')->set(false)
                            ->getQuery()
                            ->execute();
                        $ids_tax=array();
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
                        unset($punits);
                        foreach($ids_tax as $id){
                            $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                                ->findOneBy(array(
                                    'id'=>$id
                                ));
                            if($punit){
                                $loc_bool=$punit->getHaslocations();
                                $taxons=$punit->getTaxonsrefs();
                                if(count($taxons)&&($loc_bool)){
                                    $need_flush=false;
                                    foreach($taxons as $taxon){
                                        if($loc_bool&&!$taxon->getHaslocations()){
                                            $taxon->setHaslocations(true);
                                            $need_flush=true;
                                        }
                                        if($need_flush){
                                            $dm->persist($taxon);
                                        }
                                        if($taxon->getIssynonym()){
                                            $taxon_valid=$taxon->getChosen();
                                            if($loc_bool&&!$taxon_valid->getHaslocations()){
                                                $taxon_valid->setHaslocations(true);
                                                $need_flush=true;
                                            }
                                            if($need_flush){
                                                $dm->persist($taxon_valid);
                                            }
                                            $parent_taxon_valid=$taxon_valid->getParent();
                                            while($parent_taxon_valid){
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
                                    $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
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
                            ->field('haslocations')->set(false)
                            ->getQuery()
                            ->execute();
                    }
                }
            }
        }
        $dm->createQueryBuilder('PlantnetDataBundle:Location')
            ->remove()
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
        //
        $this->drop_it($dm,$collection,$module,$dbname);
    }

    private function delete_other($dm,$collection,$module,$dbname,$fast=false)
    {
        $dm->createQueryBuilder('PlantnetDataBundle:Other')
            ->remove()
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
        //
        $this->drop_it($dm,$collection,$module,$dbname);
    }

    private function drop_it($dm,$collection,$module,$dbname)
    {
        //indexes
        $m=new \MongoClient($this->getContainer()->getParameter('mdb_connection_url'));
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
    }

    private function delete_module($dbname,$id,$fast=false)
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
                $connection=new \MongoClient($this->getContainer()->getParameter('mdb_connection_url'));
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
        //
        switch($module->getType()){
            case 'image':
                $this->delete_image($dm,$collection,$module,$dbname,$fast);
                break;
            case 'locality':
                $this->delete_locality($dm,$collection,$module,$dbname,$fast);
                break;
            case 'other':
                $this->delete_other($dm,$collection,$module,$dbname,$fast);
                break;
            case 'text':
                $this->delete_punit($dm,$collection,$module,$dbname);
                break;
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
                $connection=new \MongoClient($this->getContainer()->getParameter('mdb_connection_url'));
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