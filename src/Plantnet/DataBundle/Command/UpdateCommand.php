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
                    $s=microtime(true);
                    switch($module->getType()){
                        case 'text':
                            $module=$this->update_punit($dm,$module,$dbname,$usermail);
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

    private function update_other($dm,$module)
    {
        \MongoCursor::$timeout=-1;
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

    private function reset_taxo($dm,$module)
    {
        \MongoCursor::$timeout=-1;
        // remove old taxa' refs
        $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->update()
            ->multiple(true)
            ->field('module')->references($module)
            ->field('taxon')->unsetField()
            ->field('taxonsrefs')->unsetField()
            ->getQuery()
            ->execute();
        $sub_modules=$module->getChildren();
        foreach($sub_modules as $sub){
            if($sub->getType()=='image'){
                $dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->update()
                    ->multiple(true)
                    ->field('module')->references($sub)
                    ->field('taxonsrefs')->unsetField()
                    ->getQuery()
                    ->execute();
            }
            elseif($sub->getType()=='locality'){
                $dm->createQueryBuilder('PlantnetDataBundle:Location')
                    ->update()
                    ->multiple(true)
                    ->field('module')->references($sub)
                    ->field('taxonsrefs')->unsetField()
                    ->getQuery()
                    ->execute();
            }
        }
        // remove old taxa
        $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->remove()
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
    }

    private function update_image($dm,$module)
    {
        \MongoCursor::$timeout=-1;
        $idmodule=$module->getId();
        $parent_to_update=null;
        if($module->getType()=='image'||$module->getType()=='locality'){
            if($module->getParent()&&$module->getParent()->getDeleting()===false){
                $parent_to_update=$module->getParent()->getId();
                $this->reset_taxo($dm,$module->getParent());
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
        //update ref
        if($module_parent){
            $has_img_child=false;
            $img_child_ids=array();
            $tmp_children=$module_parent->getChildren();
            if(count($tmp_children)){
                foreach($tmp_children as $tmp_child){
                    if($tmp_child->getType()=='image'&&$tmp_child->getId()!=$idmodule){
                        $has_img_child=true;
                        $img_child_ids[]=$tmp_child->getId();
                    }
                }
            }
            //images
            \MongoCursor::$timeout=-1;
            if($has_img_child){
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
        \MongoCursor::$timeout=-1;
        $idmodule=$module->getId();
        $parent_to_update=null;
        if($module->getType()=='image'||$module->getType()=='locality'){
            if($module->getParent()&&$module->getParent()->getDeleting()===false){
                $parent_to_update=$module->getParent()->getId();
                $this->reset_taxo($dm,$module->getParent());
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
            $loc_child_ids=array();
            $tmp_children=$module_parent->getChildren();
            if(count($tmp_children)){
                foreach($tmp_children as $tmp_child){
                    if($tmp_child->getType()=='locality'&&$tmp_child->getId()!=$idmodule){
                        $has_loc_child=true;
                        $loc_child_ids[]=$tmp_child->getId();
                    }
                }
            }
            //locations
            \MongoCursor::$timeout=-1;
            if($has_loc_child){
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
						if ($value<-180||$value>180) {
							$geo_error=true;
						} else {
							$location->setLongitude($value);
	                        $coordinates->setX($value);
						}
                        break;
                    case 'lat':
                        if(strlen($value)==0){
                            $geo_error=true;
                        }
                        $value=str_replace(',','.',$value);
                        $value=floatval($value);
						if ($value<-90||$value>90) {
							$geo_error=true;
						} else {
							$location->setLatitude($value);
	                        $coordinates->setY($value);
						}
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

    private function update_punit($dm,$module,$dbname,$usermail)
    {
        \MongoCursor::$timeout=-1;
        $idmodule=$module->getId();
        $this->reset_taxo($dm,$module);
        $csvfile=__DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'.csv';
        $handle=fopen($csvfile,"r");
        $columns=fgetcsv($handle,0,";");
        $fields=array();
        $attributes=$module->getProperties();
        foreach($attributes as $field){
            $fields[]=$field;
        }
        //Punit identifiers in the csv file
        $csv_ids=array();
        while(($data=fgetcsv($handle,0,';'))!==false){
            $num=count($data);
            for($c=0;$c<$num;$c++){
                if($fields[$c]->getType()=='idmodule'){
                    $value=trim($this->data_encode($data[$c]));
                    $csv_ids[]=$value;
                }
            }
        }
        fclose($handle);
        //get existing ids in database
        $db_ids=array();
        $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->hydrate(false)
            ->select('identifier')
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
        foreach($punits as $id){
            $db_ids[]=$id['identifier'];
        }
        $punits=null;
        unset($punits);
        $ids_to_remove=array_diff($db_ids,$csv_ids);
        if(count($ids_to_remove)){
            $sub_ids_to_remove=array_chunk($ids_to_remove,100);
            foreach($sub_ids_to_remove as $tab_ids){
                //delete
                //deletes punits where id is not in csv_ids
                $dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->remove()
                    ->field('identifier')->in($tab_ids)
                    ->getQuery()
                    ->execute();
                //cascade doesnt work !?
                $dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->remove()
                    ->field('idparent')->in($tab_ids)
                    ->getQuery()
                    ->execute();
                $dm->createQueryBuilder('PlantnetDataBundle:Location')
                    ->remove()
                    ->field('idparent')->in($tab_ids)
                    ->getQuery()
                    ->execute();
                $dm->createQueryBuilder('PlantnetDataBundle:Other')
                    ->remove()
                    ->field('idparent')->in($tab_ids)
                    ->getQuery()
                    ->execute();
            }
        }
        $csv_ids=null;
        unset($csv_ids);
        $db_ids=null;
        unset($db_ids);
        $ids_to_remove=null;
        unset($ids_to_remove);
        //update / create Punits
        $handle=fopen($csvfile,"r");
        $columns=fgetcsv($handle,0,";");
        $batchSize=100;
        $size=0;
        while(($data=fgetcsv($handle,0,';'))!==false){
            $csv_id=null;
            $num=count($data);
            for($c=0;$c<$num;$c++){
                if($fields[$c]->getType()=='idmodule'){
                    $value=trim($this->data_encode($data[$c]));
                    $csv_id=$value;
                }
            }
            if($csv_id){
                $update=true;
                $subupdate=false;
                //update
                //updates punits where id is in csv_ids
                $plantunit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                    ->findOneBy(array(
                        'module.id'=>$module->getId(),
                        'identifier'=>$csv_id
                    ));
                //create
                //creates punits where csv_id is not in id
                if(!$plantunit){
                    $update=false;
                    $plantunit=new Plantunit();
                    $plantunit->setModule($module);
                }
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
                        case 'idmodule':
                            $plantunit->setIdentifier($value.'');
                            break;
                        case 'idparent':
                            $plantunit->setIdparent($value.'');
                            break;
                        case 'title1':
                            if($update&&$plantunit->getTitle1()!=$value.''){
                                $subupdate=true;
                            }
                            $plantunit->setTitle1($value.'');
                            break;
                        case 'title2':
                            if($update&&$plantunit->getTitle2()!=$value.''){
                                $subupdate=true;
                            }
                            $plantunit->setTitle2($value.'');
                            break;
                        case 'title3':
                            if($update&&$plantunit->getTitle3()!=$value.''){
                                $subupdate=true;
                            }
                            $plantunit->setTitle3($value.'');
                            break;
                    }
                }
                $plantunit->setAttributes($attributes);
                $dm->persist($plantunit);
                $size++;
                //
                if($update&&$subupdate){
                    $dm->createQueryBuilder('PlantnetDataBundle:Image')
                        ->update()
                        ->multiple(true)
                        ->field('plantunit')->references($plantunit)
                        ->field('title1')->set($plantunit->getTitle1())
                        ->field('title2')->set($plantunit->getTitle2())
                        ->field('title3')->set($plantunit->getTitle3())
                        ->getQuery()
                        ->execute();
                    $dm->createQueryBuilder('PlantnetDataBundle:Location')
                        ->update()
                        ->multiple(true)
                        ->field('plantunit')->references($plantunit)
                        ->field('title1')->set($plantunit->getTitle1())
                        ->field('title2')->set($plantunit->getTitle2())
                        ->field('title3')->set($plantunit->getTitle3())
                        ->getQuery()
                        ->execute();
                }
                //
                if($size>=$batchSize){
                    $dm->flush();
                    $dm->clear();
                    gc_collect_cycles();
                    $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                    $size=0;
                }
            }
        }
        fclose($handle);
        $dm->flush();
        $dm->clear();
        gc_collect_cycles();
        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
        //nb rows module
        $count=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->field('module')->references($module)
            ->getQuery()
            ->execute()
            ->count();
        $module->setNbrows($count);
        $dm->persist($module);
        //nb rows children
        $children=$module->getChildren();
        if(count($children)){
            foreach($children as $child){
                if($child->getType()=='image'){
                    $count=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                        ->field('module')->references($child)
                        ->getQuery()
                        ->execute()
                        ->count();
                    $child->setNbrows($count);
                    $dm->persist($child);
                }
                elseif($child->getType()=='locality'){
                    $count=$dm->createQueryBuilder('PlantnetDataBundle:Location')
                        ->field('module')->references($child)
                        ->getQuery()
                        ->execute()
                        ->count();
                    $child->setNbrows($count);
                    $dm->persist($child);
                }
                elseif($child->getType()=='other'){
                    $count=$dm->createQueryBuilder('PlantnetDataBundle:Other')
                        ->field('module')->references($child)
                        ->getQuery()
                        ->execute()
                        ->count();
                    $child->setNbrows($count);
                    $dm->persist($child);
                }
            }
        }
        //flush it !
        $dm->flush();
        $dm->clear();
        gc_collect_cycles();
        $module=$dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
        //
        return $module;
    }
}
