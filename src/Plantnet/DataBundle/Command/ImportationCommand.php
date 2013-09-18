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
            $module=$dm->getRepository('PlantnetDataBundle:Module')
                ->find($idmodule);
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
                /*
                 * Initialise the metrics
                 */
                //echo "Memory usage before: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
                $s=microtime(true);
                $batchSize=200;
                $rowCount=0;
                $errorCount=0;
                while(($data=fgetcsv($handle,0,';'))!==FALSE){
                    $num=count($data);
                    if($module->getType()=='image'){
                        $image=new Image();
                        $attributes=array();
                        for($c=0;$c<$num;$c++){
                            $value=trim($this->data_encode($data[$c]));
                            $attributes[$fields[$c]->getId()]=$value;
                            switch($fields[$c]->getType()){
                                case 'file':
                                    $image->setPath($value);
                                    break;
                                case 'copyright':
                                    $image->setCopyright($value);
                                    break;
                                case 'idparent':
                                    $image->setIdparent($value);
                                    break;
                                case 'idmodule':
                                    $image->setIdentifier($value);
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
                            $parent->setHasimages(true);
                            $dm->persist($parent);
                            $rowCount++;
                            //update Taxons
                            $taxon=$parent->getTaxon();
                            if($taxon){
                                $taxon->setHasimages(true);
                                $dm->persist($taxon);
                                $image->addTaxonsref($taxon);
                                $parent_taxon=$taxon->getParent();
                                while($parent_taxon){
                                    $parent_taxon->setHasimages(true);
                                    $dm->persist($parent_taxon);
                                    $image->addTaxonsref($parent_taxon);
                                    $parent_taxon=$parent_taxon->getParent();
                                }
                                $dm->persist($image);
                                //maj taxo valide
                                if($taxon->getIssynonym()){
                                    $taxon_valid=$taxon->getChosen();
                                    $taxon_valid->setHasimages(true);
                                    $dm->persist($taxon_valid);
                                    $parent_taxon_valid=$taxon_valid->getParent();
                                    while($parent_taxon_valid){
                                        $parent_taxon_valid->setHasimages(true);
                                        $dm->persist($parent_taxon_valid);
                                        $parent_taxon_valid=$parent_taxon_valid->getParent();
                                    }
                                }
                            }
                        }
                        else{
                            $orphans[$image->getIdparent()]=$image->getIdparent();
                            $errorCount++;
                            /*
                            $plantunit=new Plantunit();
                            $plantunit->setModule($module);
                            $plantunit->setAttributes($attributes);
                            $plantunit->setIdentifier($image->getIdentifier());
                            $dm->persist($plantunit);
                            $image->setPlantunit($plantunit);
                            $dm->persist($image);
                            */
                        }
                    }
                    elseif($module->getType()=='locality'){
                        $location=new Location();
                        $coordinates=new Coordinates();
                        $attributes=array();
                        for($c=0;$c<$num;$c++){
                            $value=trim($this->data_encode($data[$c]));
                            $attributes[$fields[$c]->getId()]=$value;
                            switch($fields[$c]->getType()){
                                case 'lon':
                                    $location->setLongitude(str_replace(',','.',$value));
                                    $coordinates->setX(str_replace(',','.',$value));
                                    break;
                                case 'lat':
                                    $location->setLatitude(str_replace(',','.',$value));
                                    $coordinates->setY(str_replace(',','.',$value));
                                    break;
                                case 'idparent':
                                    $location->setIdparent($value);
                                    break;
                                case 'idmodule':
                                    $location->setIdentifier($value);
                                    break;
                            }
                        }
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
                            $parent->setHaslocations(true);
                            $dm->persist($parent);
                            $rowCount++;
                            //update Taxons
                            $taxon=$parent->getTaxon();
                            if($taxon){
                                $taxon->setHaslocations(true);
                                $dm->persist($taxon);
                                $location->addTaxonsref($taxon);
                                $parent_taxon=$taxon->getParent();
                                while($parent_taxon){
                                    $parent_taxon->setHaslocations(true);
                                    $dm->persist($parent_taxon);
                                    $location->addTaxonsref($parent_taxon);
                                    $parent_taxon=$parent_taxon->getParent();
                                }
                                $dm->persist($location);
                                //maj taxo valide
                                if($taxon->getIssynonym()){
                                    $taxon_valid=$taxon->getChosen();
                                    $taxon_valid->setHaslocations(true);
                                    $dm->persist($taxon_valid);
                                    $parent_taxon_valid=$taxon_valid->getParent();
                                    while($parent_taxon_valid){
                                        $parent_taxon_valid->setHaslocations(true);
                                        $dm->persist($parent_taxon_valid);
                                        $parent_taxon_valid=$parent_taxon_valid->getParent();
                                    }
                                }
                            }
                        }
                        else{
                            $orphans[$location->getIdparent()]=$location->getIdparent();
                            $errorCount++;
                            /*
                            $plantunit=new Plantunit();
                            $plantunit->setModule($module);
                            $plantunit->setAttributes($attributes);
                            $plantunit->setIdentifier($location->getIdentifier());
                            $plantunit->setTitle1($location->getTitle1());
                            $plantunit->setTitle2($location->getTitle2());
                            $plantunit->setTitle3($location->getTitle3());
                            $dm->persist($plantunit);
                            $location->setPlantunit($plantunit);
                            $dm->persist($location);
                            */
                        }
                    }
                    elseif($module->getType()=='other'){
                        $other=new Other();
                        $attributes=array();
                        for($c=0;$c<$num;$c++){
                            $value=trim($this->data_encode($data[$c]));
                            $attributes[$fields[$c]->getId()]=$value;
                            switch($fields[$c]->getType()){
                                case 'idparent':
                                    $other->setIdparent($value);
                                    break;
                                case 'idmodule':
                                    $other->setIdentifier($value);
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
                        }
                        else{
                            $orphans[$other->getIdparent()]=$other->getIdparent();
                            $errorCount++;
                            /*
                            $plantunit=new Plantunit();
                            $plantunit->setModule($module);
                            $plantunit->setAttributes($attributes);
                            $plantunit->setIdentifier($other->getIdentifier());
                            $dm->persist($plantunit);
                            $other->setPlantunit($plantunit);
                            $dm->persist($other);
                            */
                        }
                    }
                    if(($rowCount % $batchSize)==0){
                        $dm->flush();
                        $dm->clear();
                        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                    }
                }
                $module->setNbrows($rowCount);
                $module->setUpdating(false);
                $dm->persist($module);
                $dm->flush();
                $dm->clear();
                //echo "Memory usage after: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
                $e=microtime(true);
                echo ' Inserted '.$rowCount.' objects in '.($e-$s).' seconds'.PHP_EOL;
                fclose($handle);
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
                ))
            ;
            $this->getContainer()->get('mailer')->send($message_mail);
            $spool=$this->getContainer()->get('mailer')->getTransport()->getSpool();
            $transport=$this->getContainer()->get('swiftmailer.transport.real');
            $spool->flushQueue($transport);
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