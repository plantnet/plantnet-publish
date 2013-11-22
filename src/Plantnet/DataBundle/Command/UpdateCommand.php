<?php

namespace Plantnet\DataBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Plantnet\DataBundle\Document\Collection,
    Plantnet\DataBundle\Document\Module,
    Plantnet\DataBundle\Document\Plantunit,
    Plantnet\DataBundle\Document\Property,
    Plantnet\DataBundle\Document\Image,
    Plantnet\DataBundle\Document\Location,
    Plantnet\DataBundle\Document\Coordinates,
    Plantnet\DataBundle\Document\Other,
    Plantnet\DataBundle\Document\Taxon;

ini_set('memory_limit','-1');

class UpdateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('publish:update')
            ->setDescription('import new data from csv')
            ->addArgument('module',InputArgument::REQUIRED,'Specify a module ID')
            ->addArgument('dbname',InputArgument::REQUIRED,'Specify a database name')
            ->addArgument('usermail',InputArgument::REQUIRED,'Specify a user e-mail')
        ;
    }

    protected function execute(InputInterface $input,OutputInterface $output)
    {
        $idmodule=$input->getArgument('module');
        $dbname=$input->getArgument('dbname');
        $usermail=$input->getArgument('usermail');
        if($idmodule&&$dbname&&$usermail){
            $error='';
            $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($dbname);
            $configuration=$dm->getConnection()->getConfiguration();
            $configuration->setLoggerCallable(null);
            \MongoCursor::$timeout=-1;
            $module=$dm->getRepository('PlantnetDataBundle:Module')
                ->find($idmodule);
            if(!$module){
                $error='Unable to find Module entity.';
            }
            if(empty($error)){
                $properties=$module->getProperties();
                $nb_properties=count($properties);
                /*
                 * Open the uploaded csv
                 */
                $csvfile=__DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'.csv';
                $handle=fopen($csvfile,"r");
                $field=fgetcsv($handle,0,";");
                $nb_columns=0;
                foreach($field as $col){
                    $nb_columns++;
                }
                fclose($handle);
                if($nb_columns!=$nb_properties){
                    $error='The number of columns in the CSV file does not match the number of columns in this module.';
                }
                if(empty($error)){
                    $rowCount=0;
                    $s=microtime(true);
                    switch($module->getType()){
                        case 'text':
                            break;
                        case 'image':
                            $module=$this->update_image($dm,$module);
                            break;
                        case 'locality':
                            $module=$this->update_locality($dm,$module);
                            break;
                        case 'other':
                            $module=$this->update_other($dm,$module);
                            break;
                    }
                    $e=microtime(true);
                    $module->setUpdating(false);
                    $dm->persist($module);
                    $dm->flush();
                    $dm->clear();
                    if(file_exists($csvfile)){
                        unlink($csvfile);
                    }
                    $message='Importation Success: '.$module->getNbrows().' objects imported in '.($e-$s).' seconds';
                    $message.="\n";
                }
                else{
                    $message=$error;
                }
            }
            else{
                $message=$error;
            }
            $message_mail=\Swift_Message::newInstance()
                ->setSubject('Publish : task ended')
                ->setFrom($this->getContainer()->getParameter('from_email_adress'))
                ->setTo($usermail)
                ->setBody($message.$this->getContainer()->get('templating')->render(
                    'PlantnetDataBundle:Backend\Mail:task.txt.twig'
                ));
            $this->getContainer()->get('mailer')->send($message_mail);
            $spool=$this->getContainer()->get('mailer')->getTransport()->getSpool();
            $transport=$this->getContainer()->get('swiftmailer.transport.real');
            $spool->flushQueue($transport);
            //
            $connection=new \MongoClient();
            $db=$connection->$dbname;
            $db->Module->update(array('_id'=>new \MongoId($idmodule)),array(
                '$set'=>array(
                    'updating'=>false
                )
            ));
        }
    }

    protected function data_encode($data)
    {
        $data_encoding=mb_detect_encoding($data);
        if($data_encoding=="UTF-8"&&mb_check_encoding($data,"UTF-8")){
            $format=$data;
        }
        else{
            $format=utf8_encode($data);
        }
        return $format;
    }

    private function update_other($dm,$module)
    {
        $idmodule=$module->getId();
        //remove old data
        $dm->createQueryBuilder('PlantnetDataBundle:Other')
            ->remove()
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
        //add new data
        $csvfile=__DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'.csv';
        $handle=fopen($csvfile,"r");
        $columns=fgetcsv($handle,0,";");
        $fields=array();
        $attributes=$module->getProperties();
        foreach($attributes as $field){
            $fields[]=$field;
        }
        $batchSize=100;
        $size=0;
        $rowCount=0;
        while(($data=fgetcsv($handle,0,';'))!==false){
            $num=count($data);
            $other=new Other();
            $attributes=array();
            for($c=0;$c<$num;$c++){
                $value=trim($this->data_encode($data[$c]));
                //check for int or float value
                if(is_numeric($value)){
                    $tmp_value=intval($value);
                    if($value==$tmp_value){
                        $value=$tmp_value;
                    }
                    else{
                        $tmp_value=floatval($value);
                        if($value==$tmp_value){
                            $value=$tmp_value;
                        }
                    }
                }
                //
                $attributes[$fields[$c]->getId()]=$value;
                switch($fields[$c]->getType()){
                    case 'idparent':
                        $other->setIdparent($value.'');
                        break;
                    case 'idmodule':
                        $other->setIdentifier($value.'');
                        break;
                }
            }
            $other->setProperty($attributes);
            $other->setModule($module);
            $parent=null;
            if($module->getParent()){
                $parent_q=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->field('module.id')->equals($module->getParent()->getId())
                    ->field('identifier')->equals($other->getIdparent())
                    ->getQuery()
                    ->execute();
                foreach($parent_q as $p){
                    $parent=$p;
                }
            }
            if($parent){
                $other->setPlantunit($parent);
                $dm->persist($other);
                $rowCount++;
                $size++;
                if($size>=$batchSize){
                    $dm->flush();
                    $dm->clear();
                    gc_collect_cycles();
                    $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                    $size=0;
                }
            }
            else{
                $dm->detach($other);
            }
        }
        fclose($handle);
        $module->setNbrows($rowCount);
        $dm->persist($module);
        $dm->flush();
        $dm->clear();
        gc_collect_cycles();
        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
        return $module;
    }

    private function update_image($dm,$module)
    {
        $idmodule=$module->getId();
        $parent_to_update=null;
        if($module->getType()=='image'||$module->getType()=='locality'){
            if($module->getParent()&&$module->getParent()->getDeleting()===false){
                $parent_to_update=$module->getParent()->getId();
            }
        }
        if($parent_to_update==null){
            return $module;
        }
        //remove old data
        $dm->createQueryBuilder('PlantnetDataBundle:Image')
            ->remove()
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
        $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
        if($module_parent){
            $has_img_child=false;
            $tmp_children=$module_parent->getChildren();
            if(count($tmp_children)){
                foreach($tmp_children as $tmp_child){
                    if($tmp_child->getType()=='image'){
                        $has_img_child=true;
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
                $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
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
                unset($punits);
                foreach($ids_tax as $id){
                    $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                        ->findOneBy(array(
                            'id'=>$id
                        ));
                    if($punit){
                        $img_bool=$punit->getHasimages();
                        $taxons=$punit->getTaxonsrefs();
                        if(count($taxons)&&$img_bool){
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
        //add new data
        $csvfile=__DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'.csv';
        $handle=fopen($csvfile,"r");
        $columns=fgetcsv($handle,0,";");
        $fields=array();
        $attributes=$module->getProperties();
        foreach($attributes as $field){
            $fields[]=$field;
        }
        $batchSize=100;
        $size=0;
        $rowCount=0;
        while(($data=fgetcsv($handle,0,';'))!==false){
            $num=count($data);
            $image=new Image();
            $attributes=array();
            for($c=0;$c<$num;$c++){
                $value=trim($this->data_encode($data[$c]));
                //check for int or float value
                if(is_numeric($value)){
                    $tmp_value=intval($value);
                    if($value==$tmp_value){
                        $value=$tmp_value;
                    }
                    else{
                        $tmp_value=floatval($value);
                        if($value==$tmp_value){
                            $value=$tmp_value;
                        }
                    }
                }
                //
                $attributes[$fields[$c]->getId()]=$value;
                switch($fields[$c]->getType()){
                    case 'file':
                        $image->setPath($value.'');
                        break;
                    case 'copyright':
                        $image->setCopyright($value.'');
                        break;
                    case 'idparent':
                        $image->setIdparent($value.'');
                        break;
                    case 'idmodule':
                        $image->setIdentifier($value.'');
                        break;
                }
            }
            $image->setProperty($attributes);
            $image->setModule($module);
            $parent=null;
            if($module->getParent()){
                $parent_q=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->field('module.id')->equals($module->getParent()->getId())
                    ->field('identifier')->equals($image->getIdparent())
                    ->getQuery()
                    ->execute();
                foreach($parent_q as $p){
                    $parent=$p;
                }
            }
            if($parent){
                $image->setPlantunit($parent);
                $image->setTitle1($parent->getTitle1());
                $image->setTitle2($parent->getTitle2());
                $image->setTitle3($parent->getTitle3());
                $dm->persist($image);
                $rowCount++;
                $size++;
                if(!$parent->getHasimages()){
                    $parent->setHasimages(true);
                    $dm->persist($parent);
                    $size++;
                }
                //update Taxons
                $taxons=$parent->getTaxonsrefs();
                if(count($taxons)){
                    foreach($taxons as $taxon){
                        if(!$taxon->getHasimages()){
                            $taxon->setHasimages(true);
                            $dm->persist($taxon);
                            $size++;
                        }
                        if($taxon->getIssynonym()){
                            $taxon_valid=$taxon->getChosen();
                            if(!$taxon_valid->getHasimages()){
                                $taxon_valid->setHasimages(true);
                                $dm->persist($taxon_valid);
                                $size++;
                            }
                            $parent_taxon_valid=$taxon_valid->getParent();
                            while($parent_taxon_valid){
                                if(!$parent_taxon_valid->getHasimages()){
                                    $parent_taxon_valid->setHasimages(true);
                                    $dm->persist($parent_taxon_valid);
                                    $size++;
                                }
                                $parent_taxon_valid=$parent_taxon_valid->getParent();
                            }
                        }
                    }
                }
                if($size>=$batchSize){
                    $dm->flush();
                    $dm->clear();
                    gc_collect_cycles();
                    $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                    $size=0;
                }
            }
            else{
                $dm->detach($image);
            }
        }
        fclose($handle);
        $module->setNbrows($rowCount);
        $dm->persist($module);
        $dm->flush();
        $dm->clear();
        gc_collect_cycles();
        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
        return $module;
    }

    private function update_locality($dm,$module)
    {
        $idmodule=$module->getId();
        $parent_to_update=null;
        if($module->getType()=='image'||$module->getType()=='locality'){
            if($module->getParent()&&$module->getParent()->getDeleting()===false){
                $parent_to_update=$module->getParent()->getId();
            }
        }
        if($parent_to_update==null){
            return $module;
        }
        //remove old data
        $dm->createQueryBuilder('PlantnetDataBundle:Location')
            ->remove()
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
        $module_parent=$dm->getRepository('PlantnetDataBundle:Module')->find($parent_to_update);
        if($module_parent){
            $has_loc_child=false;
            $tmp_children=$module_parent->getChildren();
            if(count($tmp_children)){
                foreach($tmp_children as $tmp_child){
                    if($tmp_child->getType()=='locality'){
                        $has_loc_child=true;
                    }
                }
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
                $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
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
            if($has_loc_child){
                $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                    ->update()
                    ->multiple(true)
                    ->field('module')->references($module_parent)
                    ->field('haslocations')->set(false)
                    ->getQuery()
                    ->execute();
                $ids_tax=array();
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
                        $loc_bool=$punit->getHaslocations();
                        $taxons=$punit->getTaxonsrefs();
                        if(count($taxons)&&$loc_bool){
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
        //add new data
        $csvfile=__DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'.csv';
        $handle=fopen($csvfile,"r");
        $columns=fgetcsv($handle,0,";");
        $fields=array();
        $attributes=$module->getProperties();
        foreach($attributes as $field){
            $fields[]=$field;
        }
        $batchSize=100;
        $size=0;
        $rowCount=0;
        while(($data=fgetcsv($handle,0,';'))!==false){
            $geo_error=false;
            $num=count($data);
            $location=new Location();
            $coordinates=new Coordinates();
            $attributes=array();
            for($c=0;$c<$num;$c++){
                $value=trim($this->data_encode($data[$c]));
                //check for int or float value
                if(is_numeric($value)){
                    $tmp_value=intval($value);
                    if($value==$tmp_value){
                        $value=$tmp_value;
                    }
                    else{
                        $tmp_value=floatval($value);
                        if($value==$tmp_value){
                            $value=$tmp_value;
                        }
                    }
                }
                //
                $attributes[$fields[$c]->getId()]=$value;
                switch($fields[$c]->getType()){
                    case 'lon':
                        if(strlen($value)==0){
                            $geo_error=true;
                        }
                        $value=str_replace(',','.',$value);
                        $value=floatval($value);
                        $location->setLongitude($value);
                        $coordinates->setX($value);
                        break;
                    case 'lat':
                        if(strlen($value)==0){
                            $geo_error=true;
                        }
                        $value=str_replace(',','.',$value);
                        $value=floatval($value);
                        $location->setLatitude($value);
                        $coordinates->setY($value);
                        break;
                    case 'idparent':
                        $location->setIdparent($value.'');
                        break;
                    case 'idmodule':
                        $location->setIdentifier($value.'');
                        break;
                }
            }
            if(!$geo_error){
                $location->setCoordinates($coordinates);
                $location->setProperty($attributes);
                $location->setModule($module);
                $parent=null;
                if($module->getParent()){
                    $parent_q=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                        ->field('module.id')->equals($module->getParent()->getId())
                        ->field('identifier')->equals($location->getIdparent())
                        ->getQuery()
                        ->execute();
                    foreach($parent_q as $p){
                        $parent=$p;
                    }
                }
                if($parent){
                    $location->setPlantunit($parent);
                    $location->setTitle1($parent->getTitle1());
                    $location->setTitle2($parent->getTitle2());
                    $location->setTitle3($parent->getTitle3());
                    $dm->persist($location);
                    $rowCount++;
                    $size++;
                    if(!$parent->getHaslocations()){
                        $parent->setHaslocations(true);
                        $dm->persist($parent);
                        $size++;
                    }
                    //update Taxons
                    $taxons=$parent->getTaxonsrefs();
                    if(count($taxons)){
                        foreach($taxons as $taxon){
                            if(!$taxon->getHaslocations()){
                                $taxon->setHaslocations(true);
                                $dm->persist($taxon);
                                $size++;
                            }
                            if($taxon->getIssynonym()){
                                $taxon_valid=$taxon->getChosen();
                                if(!$taxon_valid->getHaslocations()){
                                    $taxon_valid->setHaslocations(true);
                                    $dm->persist($taxon_valid);
                                    $size++;
                                }
                                $parent_taxon_valid=$taxon_valid->getParent();
                                while($parent_taxon_valid){
                                    if(!$parent_taxon_valid->getHaslocations()){
                                        $parent_taxon_valid->setHaslocations(true);
                                        $dm->persist($parent_taxon_valid);
                                    }
                                    $parent_taxon_valid=$parent_taxon_valid->getParent();
                                }
                            }
                        }
                    }
                    if($size>=$batchSize){
                        $dm->flush();
                        $dm->clear();
                        gc_collect_cycles();
                        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                        $size=0;
                    }
                }
                else{
                    $dm->detach($location);
                }
            }
            else{
                $dm->detach($location);
            }
        }
        fclose($handle);
        $module->setNbrows($rowCount);
        $dm->persist($module);
        $dm->flush();
        $dm->clear();
        gc_collect_cycles();
        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
        return $module;
    }
}