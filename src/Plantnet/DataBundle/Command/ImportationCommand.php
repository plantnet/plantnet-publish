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
    Plantnet\DataBundle\Document\Imageurl,
    Plantnet\DataBundle\Document\Location,
    Plantnet\DataBundle\Document\Coordinates,
    Plantnet\DataBundle\Document\Other;

ini_set('memory_limit','-1');

class ImportationCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('publish:importation')
            ->setDescription('import data from csv')
            ->addArgument('collection',InputArgument::REQUIRED,'Specify a collection ID')
            ->addArgument('module',InputArgument::REQUIRED,'Specify a module ID')
            ->addArgument('dbname',InputArgument::REQUIRED,'Specify a database name')
            ->addArgument('usermail',InputArgument::REQUIRED,'Specify a user e-mail')
        ;
    }

    protected function execute(InputInterface $input,OutputInterface $output)
    {

        $id=$input->getArgument('collection');
        $idmodule=$input->getArgument('module');
        $dbname=$input->getArgument('dbname');
        $usermail=$input->getArgument('usermail');
        if($id&&$idmodule&&$dbname&&$usermail){
            $orphans=array();
            $error='';
            $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($dbname);
            $configuration=$dm->getConnection()->getConfiguration();
            $configuration->setLoggerCallable(null);
            \MongoCursor::$timeout=-1;
            $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
            if(!$module){
                $error='Unable to find Module entity.';
            }
            if($module->getType()=='text'){
                $error='Module entity: Wrong type.';
            }
            if(empty($error)){
                /*
                 * Open the uploaded csv
                 */
                $csvfile=__DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'.csv';
                $handle=fopen($csvfile,"r");
                /*
                 * Get the module properties
                 */
                $columns=fgetcsv($handle,0,";");
                $fields=array();
                $attributes=$module->getProperties();
                foreach($attributes as $field){
                    $fields[]=$field;
                }
                $s=microtime(true);
                $batchSize=100;
                $size=0;
                $rowCount=0;
                $errorCount=0;
                if($module->getType()=='imageurl'){
                    while(($data=fgetcsv($handle,0,';'))!==false){
                        $num=count($data);
                        $imageurl=new Imageurl();
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
                            $attributes[$fields[$c]->getId()]=$value;

                            switch($fields[$c]->getType()){
                                case 'url':
                                    $imageurl->setUrl($value.'');
                                    $imageurl->setPath($value.'');
                                    break;
                                case 'copyright':
                                    $imageurl->setCopyright($value.'');
                                    break;
                                case 'idparent':
                                    $imageurl->setIdparent($value.'');
                                    break;
                                case 'idmodule':
                                    $imageurl->setIdentifier($value.'');
                                    break;
                            }
                        }
                        $imageurl->setProperty($attributes);
                        $imageurl->setModule($module);
                        $parent=null;
                        if($module->getParent()){
                            $parent_q=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                                ->field('module.id')->equals($module->getParent()->getId())
                                ->field('identifier')->equals($imageurl->getIdparent())
                                ->getQuery()
                                ->execute();
                            foreach($parent_q as $p){
                                $parent=$p;
                            }
                        }
                        if($parent){
                            $imageurl->setPlantunit($parent);
                            $imageurl->setTitle1($parent->getTitle1());
                            $imageurl->setTitle2($parent->getTitle2());
                            $imageurl->setTitle3($parent->getTitle3());
                            $dm->persist($imageurl);
                            $rowCount++;
                            $size++;
                            if(!$parent->getHasimagesurl()){
                                $parent->setHasimagesurl(true);
                                $dm->persist($parent);
                                $size++;
                            }
                            //update Taxons
                            $taxons=$parent->getTaxonsrefs();
                            if(count($taxons)){
                                foreach($taxons as $taxon){
                                    if(!$taxon->getHasimagesurl()){
                                        $taxon->setHasimagesurl(true);
                                        $dm->persist($taxon);
                                        $size++;
                                    }
                                    if($taxon->getIssynonym()){
                                        $taxon_valid=$taxon->getChosen();
                                        if(!$taxon_valid->getHasimagesurl()){
                                            $taxon_valid->setHasimagesurl(true);
                                            $dm->persist($taxon_valid);
                                            $size++;
                                        }
                                        $parent_taxon_valid=$taxon_valid->getParent();
                                        while($parent_taxon_valid){
                                            if(!$parent_taxon_valid->getHasimagesurl()){
                                                $parent_taxon_valid->setHasimagesurl(true);
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
                            $orphans[$imageurl->getIdparent()]=$imageurl->getIdparent();
                            $errorCount++;
                            $dm->detach($imageurl);
                        }
                    }
                    $dm->flush();
                    $dm->clear();
                    gc_collect_cycles();
                    $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                }elseif($module->getType()=='image'){
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
                            $orphans[$image->getIdparent()]=$image->getIdparent();
                            $errorCount++;
                            $dm->detach($image);
                        }
                    }
                    $dm->flush();
                    $dm->clear();
                    gc_collect_cycles();
                    $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                }
                elseif($module->getType()=='locality'){
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
                                $orphans[$location->getIdparent()]=$location->getIdparent();
                                $errorCount++;
                                $dm->detach($location);
                            }
                        }
                        else{
                            $orphans[$location->getIdparent()]=$location->getIdparent();
                            $errorCount++;
                            $dm->detach($location);
                        }
                    }
                    $dm->flush();
                    $dm->clear();
                    gc_collect_cycles();
                    $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                }
                elseif($module->getType()=='other'){
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
                            $orphans[$other->getIdparent()]=$other->getIdparent();
                            $errorCount++;
                            $dm->detach($other);
                        }
                    }
                    $dm->flush();
                    $dm->clear();
                    gc_collect_cycles();
                    $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                }
                fclose($handle);
                $module->setNbrows($rowCount);
                $module->setUpdating(false);
                $dm->persist($module);
                $dm->flush();
                $dm->clear();
                $e=microtime(true);
                echo ' Inserted '.$rowCount.' objects in '.($e-$s).' seconds'.PHP_EOL;
                if(file_exists($csvfile)){
                   unlink($csvfile);
                }
                $message='Importation Success: '.$rowCount.' objects imported in '.($e-$s).' seconds';
                $message.="\n";
                $message.='Importation Error: '.$errorCount;
                if(count($orphans)){
                    $message.="\n".'These "id parent" do not exist:'."\n";
                    $message.=implode(', ',$orphans);
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
            $connection=new \MongoClient($this->getContainer()->getParameter('mdb_connection_url'));
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
}