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
    Plantnet\DataBundle\Document\Other,
    Plantnet\DataBundle\Document\Taxon;

ini_set('memory_limit','-1');

class TaxonomizeCommand extends ContainerAwareCommand
{
    private $tmp_indexes=array();

    protected function configure()
    {
        $this
            ->setName('publish:taxon')
            ->setDescription('create taxons entities')
            ->addArgument('action',InputArgument::REQUIRED,'Specify the action (taxo or syn)')
            ->addArgument('id',InputArgument::REQUIRED,'Specify the ID of the module entity')
            ->addArgument('dbname',InputArgument::REQUIRED,'Specify a database name')
            ->addArgument('usermail',InputArgument::REQUIRED,'Specify a user e-mail')
            ->addArgument('clean',InputArgument::OPTIONAL,'Clean Syn and Desc')
        ;
    }

    protected function execute(InputInterface $input,OutputInterface $output)
    {
        $actions=array(
            'taxo',
            'syn',
            'desc',
            'undesc'
        );
        $action=$input->getArgument('action');
        $id=$input->getArgument('id');
        $dbname=$input->getArgument('dbname');
        $usermail=$input->getArgument('usermail');
        $clean=false;
        if($action&&in_array($action,$actions)){
            if($action=='taxo'&&$clean=$input->getArgument('clean')){
                $clean=true;
            }
            if($id&&$dbname&&$usermail){
                $this->taxonomize($action,$dbname,$id,$usermail,$clean);
            }
        }
    }

    private function populate($db,$module,$taxo)
    {
        $tmp_nb=1;
        $tab_tax=array();
        $fields=array(
            'hasimages'=>1,
            'haslocations'=>1
        );
        $meta=array();
        $field_ordered=array();
        foreach($taxo as $level=>$data){
            $fields['attributes.'.$data[0]]=1;
            $meta[$data[0]]=array(
                'level'=>$level,
                'label'=>$data[1]
            );
            $field_ordered[]=$data[0];
            if($tmp_nb==1){
                $this->tmp_indexes['tmptmp'.$tmp_nb]=array('attributes.'.$data[0]=>1);
            }
            else{
                $this->tmp_indexes['tmptmp'.$tmp_nb]=array_merge(
                    $this->tmp_indexes['tmptmp'.($tmp_nb-1)],
                    array('attributes.'.$data[0]=>1)
                );
            }
            $tmp_nb++;
        }
        $data=$db->Plantunit->find(array(
            'module.$id'=>new \MongoId($module->getId())
        ),$fields);
        foreach($data as $line){
            //find last non null value => last non null rank
            $last_non_null_rank=0;
            foreach($field_ordered as $attr_value_tmp){
                $checked_value=$line['attributes'][$attr_value_tmp];
                if(!empty($checked_value)){
                    $last_non_null_rank=$meta[$attr_value_tmp]['level'];
                }
            }
            //check data
            $img=(isset($line['hasimages'])&&$line['hasimages'])?true:false;
            $loc=(isset($line['haslocations'])&&$line['haslocations'])?true:false;
            $id_parent='';
            $identifier='';
            foreach($field_ordered as $attr_value){
                $column=$attr_value;
                $value=$line['attributes'][$column];
                if(empty($value)&&$meta[$column]['level']<$last_non_null_rank){
                    $value='-';
                }
                if(!empty($value)){
                    if(empty($id_parent)){
                        $tmp_id_parent='|';
                    }
                    else{
                        $tmp_id_parent=$id_parent;
                    }
                    if(!empty($identifier)){
                        $identifier.=' - ';
                    }
                    $identifier.=$value;
                    if(!isset($tab_tax[$tmp_id_parent])){
                        $tab_tax[$tmp_id_parent]=array();
                    }
                    if(!isset($tab_tax[$tmp_id_parent][$identifier])){
                        $tab_tax[$tmp_id_parent][$identifier]=array(
                            'column'=>$column,
                            'name'=>$value,
                            'level'=>$meta[$column]['level'],
                            'label'=>$meta[$column]['label'],
                            'hasimages'=>$img,
                            'haslocations'=>$loc,
                            'nb'=>1
                        );
                    }
                    else{
                        $tab_tax[$tmp_id_parent][$identifier]['hasimages']=$tab_tax[$tmp_id_parent][$identifier]['hasimages']||$img;
                        $tab_tax[$tmp_id_parent][$identifier]['haslocations']=$tab_tax[$tmp_id_parent][$identifier]['haslocations']||$loc;
                        $tab_tax[$tmp_id_parent][$identifier]['nb']=$tab_tax[$tmp_id_parent][$identifier]['nb']+1;
                    }
                    if($tmp_id_parent=='|'){
                        $id_parent=$value;
                    }
                    else{
                        $id_parent.=' - '.$value;
                    }
                }
            }
        }
        $fields=null;
        $meta=null;
        $data=null;
        $field_ordered=null;
        return $tab_tax;
    }

    private function save($db,$dbname,$dm,$module,$taxo,$tab_taxons,$parent_id='|',$parent=null,$filters=array())
    {
        foreach($tab_taxons as $id_parent=>$taxons){
            if($id_parent==$parent_id){
                foreach($taxons as $identifier=>$tax){
                    $cur_filters=array(
                        'attributes.'.$tax['column']=>$tax['name'],
                        'module.$id'=>new \MongoId($module->getId())
                    );
                    $cur_filters=array_merge($cur_filters,$filters);
                    $taxon=new Taxon();
                    $taxon->setIdentifier($identifier);
                    $taxon->setName($tax['name']);
                    $taxon->setLabel($tax['label']);
                    $taxon->setLevel($tax['level']);
                    $taxon->setModule($module);
                    $taxon->setNbpunits($tax['nb']);
                    $taxon->setIssynonym(false);
                    $taxon->setHasimages($tax['hasimages']);
                    $taxon->setHaslocations($tax['haslocations']);
                    if($parent){
                        $taxon->setParent($parent);
                    }
                    if(isset($tab_taxons[$identifier])){
                        $taxon->setHaschildren(true);
                        $dm->persist($taxon);
                        $dm->flush();
                        $this->save($db,$dbname,$dm,$module,$taxo,$tab_taxons,$identifier,$taxon,array_merge($filters,array('attributes.'.$tax['column']=>$tax['name'])));
                    }
                    else{
                        $dm->persist($taxon);
                        $dm->flush();
                    }
                    // Set ref Punits // Taxon
                    $db->Plantunit->update($cur_filters,array(
                        '$addToSet'=>array(
                            'taxonsrefs'=>array(
                                '$ref'=>'Taxon',
                                '$id'=>new \MongoId($taxon->getId()),
                                '$db'=>$dbname
                            )
                        )
                    ),array('multiple'=>true));
                    // free memory
                    $dm->detach($taxon);
                    $taxon=null;
                }
            }
        }
    }

    private function taxonomize($action,$dbname,$id_module,$usermail,$clean)
    {
        $error='';
        $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($dbname);
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$id_module
            ));
        if(!$module){
            $error='Unable to find Module entity.';
        }
        if($clean){
            $csv=__DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'_syn.csv';
            if(file_exists($csv)){
                unlink($csv);
            }
            $csv=__DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'_desc.csv';
            if(file_exists($csv)){
                unlink($csv);
            }
        }
        if($action=='taxo'){
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
        // load taxonomy data
        $taxo=array();
        $fields=$module->getProperties();
        foreach($fields as $field){
            if($field->getTaxolevel()){
                $taxo[$field->getTaxolevel()]=array(
                    $field->getId(),
                    $field->getTaxolabel()
                );
            }
        }
        $fields=null;
        if(count($taxo)){
            ksort($taxo);
            $first_level=key($taxo);
            end($taxo);
            $last_level=key($taxo);
            reset($taxo);
            if($action=='taxo'){
                $connection=new \MongoClient();
                $db=$connection->$dbname;
                //populate
                $tab_tax=$this->populate($db,$module,$taxo);
                //set indexes
                if(count($this->tmp_indexes)){
                    foreach($this->tmp_indexes as $i_name=>$i_index){
                        $db->Plantunit->ensureIndex($i_index,array('name'=>$i_name));
                    }
                }
                //save
                \MongoCursor::$timeout=-1;
                $this->save($db,$dbname,$dm,$module,$taxo,$tab_tax);
                $tab_tax=null;
                unset($tab_tax);
                $dm->clear();
                gc_collect_cycles();
                $module=$dm->getRepository('PlantnetDataBundle:Module')
                    ->findOneBy(array(
                        'id'=>$id_module
                    ));
                //unset indexes
                if(count($this->tmp_indexes)){
                    foreach($this->tmp_indexes as $i_name=>$i_index){
                        $db->command(array('deleteIndexes'=>'Plantunit','index'=>$i_name));
                    }
                }
                //free
                $db=null;
                $connection=null;
            }
            elseif($action=='syn'){
                // synonymy management
                $csv=__DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'_syn.csv';
                if(file_exists($csv)){
                    $cols=array();
                    $syns=array();
                    $handle=fopen($csv,"r");
                    $field=fgetcsv($handle,0,";");
                    foreach($field as $col){
                        $col_name='';
                        $cur_encoding=mb_detect_encoding($col);
                        if($cur_encoding=="UTF-8"&&mb_check_encoding($col,"UTF-8")){
                            $col_name=$col;
                        }
                        else{
                            $col_name=utf8_encode($col);
                        }
                        $cols[]=$col_name;
                    }
                    $csv_error=false;
                    foreach($taxo as $level=>$data){
                        $syns[$level]=array(
                            'col_non_valid'=>'',
                            'col_valid'=>''
                        );
                        foreach($cols as $key=>$col){
                            if(empty($data[1])){
                                $csv_error=true;
                            }
                            else{
                                if($col==$data[1]){
                                    $syns[$level]['col_non_valid']=$key;
                                }
                                if($col==$data[1].'_valid'){
                                    $syns[$level]['col_valid']=$key;
                                }
                            }
                        }
                        if(empty($syns[$level]['col_non_valid'])&&$syns[$level]['col_non_valid']!=0){
                            $csv_error=true;
                        }
                        if(empty($syns[$level]['col_valid'])&&$syns[$level]['col_valid']!=0){
                            $csv_error=true;
                        }
                    }
                    if(!$csv_error){
                        $connection=new \MongoClient();
                        $db=$connection->$dbname;
                        \MongoCursor::$timeout=-1;
                        while(($data=fgetcsv($handle,0,';'))!==false){
                            //last non null
                            $last_non_null_rank_non_valid=0;
                            $last_non_null_rank_valid=0;
                            foreach($syns as $level=>$tab){
                                $checked_value_non_valid=isset($data[$tab['col_non_valid']])?trim($data[$tab['col_non_valid']]):'';
                                $checked_value_valid=isset($data[$tab['col_valid']])?trim($data[$tab['col_valid']]):'';
                                if(!empty($checked_value_non_valid)){
                                    $last_non_null_rank_non_valid=$level;
                                }
                                if(!empty($checked_value_valid)){
                                    $last_non_null_rank_valid=$level;
                                }
                            }
                            //data
                            $non_valid_identifier='';
                            $valid_identifier='';
                            $non_valid=array();
                            $valid=array();
                            foreach($syns as $level=>$tab){
                                $string_non_valid=isset($data[$tab['col_non_valid']])?trim($data[$tab['col_non_valid']]):'';
                                if(empty($string_non_valid)&&$level<$last_non_null_rank_non_valid){
                                    $string_non_valid='-';
                                }
                                $string_valid=isset($data[$tab['col_valid']])?trim($data[$tab['col_valid']]):'';
                                if(empty($string_valid)&&$level<$last_non_null_rank_valid){
                                    $string_valid='-';
                                }
                                $cur_encoding=mb_detect_encoding($string_non_valid);
                                if($cur_encoding=="UTF-8"&&mb_check_encoding($string_non_valid,"UTF-8")){
                                    $string_non_valid=$string_non_valid;
                                }
                                else{
                                    $string_non_valid=utf8_encode($string_non_valid);
                                }
                                $cur_encoding=mb_detect_encoding($string_valid);
                                if($cur_encoding=="UTF-8"&&mb_check_encoding($string_valid,"UTF-8")){
                                    $string_valid=$string_valid;
                                }
                                else{
                                    $string_valid=utf8_encode($string_valid);
                                }
                                if($non_valid_identifier&&!empty($string_non_valid)){
                                    $non_valid_identifier.=' - ';
                                }
                                $non_valid_identifier.=$string_non_valid;
                                if($valid_identifier&&!empty($string_valid)){
                                    $valid_identifier.=' - ';
                                }
                                $valid_identifier.=$string_valid;
                                $non_valid[$level]=null;
                                if(!empty($string_non_valid)){
                                    $non_valid[$level]=array(
                                        'name'=>$string_non_valid,
                                        'identifier'=>$non_valid_identifier,
                                        'level'=>$level,
                                        'label'=>$cols[$tab['col_non_valid']]
                                    );
                                }
                                $valid[$level]=null;
                                if(!empty($string_valid)){
                                    $valid[$level]=array(
                                        'name'=>$string_valid,
                                        'identifier'=>$valid_identifier,
                                        'level'=>$level,
                                        'label'=>$cols[$tab['col_non_valid']]
                                    );
                                }
                            }
                            $last_non_valid=null;
                            foreach($non_valid as $lvl=>$check_taxon){
                                if($check_taxon){
                                    $tax=$dm->getRepository('PlantnetDataBundle:Taxon')
                                        ->findOneBy(array(
                                            'module.id'=>$module->getId(),
                                            'identifier'=>$check_taxon['identifier']
                                        ));
                                    if(!$tax){
                                        $tax=new Taxon();
                                        $tax->setIdentifier($check_taxon['identifier']);
                                        $tax->setName($check_taxon['name']);
                                        $tax->setLabel($check_taxon['label']);
                                        $tax->setLevel($check_taxon['level']);
                                        $tax->setModule($module);
                                        $tax->setNbpunits(0);
                                        $tax->setIssynonym(false);
                                        if($last_non_valid){
                                            $tax->setParent($last_non_valid);
                                            if($last_non_valid->getHaschildren()!=true){
                                                $last_non_valid->setHaschildren(true);
                                                $dm->persist($last_non_valid);
                                            }
                                        }
                                        $dm->persist($tax);
                                        $dm->flush();
                                    }
                                    $last_non_valid=$tax;
                                }
                            }
                            $last_valid=null;
                            foreach($valid as $lvl=>$check_taxon){
                                if($check_taxon){
                                    $tax=$dm->getRepository('PlantnetDataBundle:Taxon')
                                        ->findOneBy(array(
                                            'module.id'=>$module->getId(),
                                            'identifier'=>$check_taxon['identifier']
                                        ));
                                    if(!$tax){
                                        $tax=new Taxon();
                                        $tax->setIdentifier($check_taxon['identifier']);
                                        $tax->setName($check_taxon['name']);
                                        $tax->setLabel($check_taxon['label']);
                                        $tax->setLevel($check_taxon['level']);
                                        $tax->setModule($module);
                                        $tax->setNbpunits(0);
                                        $tax->setIssynonym(false);
                                        if($last_valid){
                                            $tax->setParent($last_valid);
                                            if($last_valid->getHaschildren()!=true){
                                                $last_valid->setHaschildren(true);
                                                $dm->persist($last_valid);
                                            }
                                        }
                                        $dm->persist($tax);
                                        $dm->flush();
                                    }
                                    $last_valid=$tax;
                                }
                            }
                            $exists=false;
                            if($last_non_valid->getIssynonym()&&$last_non_valid->getChosen()){
                                if($last_non_valid->getChosen()->getId()==$last_valid->getId()){
                                    $exists=true;
                                }
                            }
                            if(!$exists&&$last_valid&&$last_non_valid&&$last_valid->getIdentifier()!=$last_non_valid->getIdentifier()){
                                $last_non_valid->setIssynonym(true);
                                $last_non_valid->setChosen($last_valid);
                                if($last_non_valid->getHaschildren()){
                                    $last_valid->setHaschildren(true);
                                }
                                $last_valid->setHassynonyms(true);
                                //
                                $has_images=$last_non_valid->getHasimages();
                                $has_locations=$last_non_valid->getHaslocations();
                                $nb_to_switch=$last_non_valid->getNbpunits();
                                $parent_tmp=$last_non_valid->getParent();
                                while($parent_tmp){
                                    $parent_tmp->setNbpunits($parent_tmp->getNbpunits()-$nb_to_switch);
                                    $dm->persist($parent_tmp);
                                    $parent_tmp=$parent_tmp->getParent();
                                }
                                $last_valid->setNbpunits($last_valid->getNbpunits()+$nb_to_switch);
                                if($has_images){
                                    $last_valid->setHasimages(true);
                                }
                                if($has_locations){
                                    $last_valid->setHaslocations(true);
                                }
                                $dm->persist($last_valid);
                                $parent_tmp=$last_valid->getParent();
                                while($parent_tmp){
                                    $parent_tmp->setNbpunits($parent_tmp->getNbpunits()+$nb_to_switch);
                                    if($has_images){
                                        $parent_tmp->setHasimages(true);
                                    }
                                    if($has_locations){
                                        $parent_tmp->setHaslocations(true);
                                    }
                                    $dm->persist($parent_tmp);
                                    $parent_tmp=$parent_tmp->getParent();
                                }
                                $dm->persist($last_non_valid);
                                $dm->persist($last_valid);
                                $dm->flush();
                                $db->Plantunit->update(array(
                                    'module.$id'=>new \MongoId($module->getId()),
                                    'taxonsrefs'=>array(
                                        '$elemMatch'=>array(
                                            '$id'=>new \MongoId($last_non_valid->getId())
                                        )
                                    )
                                ),array(
                                    '$addToSet'=>array(
                                        'taxonsrefs'=>array(
                                            '$ref'=>'Taxon',
                                            '$id'=>new \MongoId($last_valid->getId()),
                                            '$db'=>$dbname
                                        )
                                    )
                                ),array('multiple'=>true));
                            }
                            $dm->clear();
                            gc_collect_cycles();
                            $module=$dm->getRepository('PlantnetDataBundle:Module')
                                ->findOneBy(array(
                                    'id'=>$id_module
                                ));
                        }
                    }
                    else{
                        $error='CSV file contains some errors...';
                    }
                }
            }
            elseif($action=='desc'){
                // description management
                $csv=__DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getAlias().'_desc.csv';
                if(file_exists($csv)){
                    $cols=array();
                    $taxa=array();
                    $desc=array();
                    $handle=fopen($csv,"r");
                    $field=fgetcsv($handle,0,";");
                    foreach($field as $col){
                        $col_name='';
                        $cur_encoding=mb_detect_encoding($col);
                        if($cur_encoding=="UTF-8"&&mb_check_encoding($col,"UTF-8")){
                            $col_name=$col;
                        }
                        else{
                            $col_name=utf8_encode($col);
                        }
                        $cols[]=$col_name;
                    }
                    $csv_error=false;
                    $used_key=array();
                    foreach($taxo as $level=>$data){
                        $taxa[$level]=array(
                            'col'=>''
                        );
                        foreach($cols as $key=>$col){
                            if($col==$data[1]){
                                $taxa[$level]['col']=$key;
                                $used_key[]=$key;
                            }
                        }
                        if(empty($taxa[$level]['col'])&&$taxa[$level]['col']!=0){
                            $csv_error=true;
                        }
                    }
                    foreach($cols as $key=>$col){
                        if(!in_array($key,$used_key)){
                            $desc[$key]=$col;
                        }
                    }
                    $used_key=null;
                    if(!count($desc)){
                        $csv_error=true;
                    }
                    if(!$csv_error){
                        $i=0;
                        $batch_size=100;
                        \MongoCursor::$timeout=-1;
                        while(($data=fgetcsv($handle,0,';'))!==false){
                            $identifier='';
                            $tax=null;
                            foreach($taxa as $level=>$tab){
                                $string=isset($data[$tab['col']])?trim($data[$tab['col']]):'';
                                $cur_encoding=mb_detect_encoding($string);
                                if($cur_encoding=="UTF-8"&&mb_check_encoding($string,"UTF-8")){
                                    $string=$string;
                                }
                                else{
                                    $string=utf8_encode($string);
                                }
                                if($identifier&&!empty($string)){
                                    $identifier.=' - ';
                                }
                                $identifier.=$string;
                                if(!empty($string)){
                                    $tax=array(
                                        'name'=>$string,
                                        'identifier'=>$identifier,
                                        'level'=>$level,
                                        'label'=>$cols[$tab['col']]
                                    );
                                }
                            }
                            if($tax){
                                $description=array();
                                $nb=0;
                                foreach($desc as $key=>$val){
                                    $string=isset($data[$key])?trim($data[$key]):'';
                                    $cur_encoding=mb_detect_encoding($string);
                                    if($cur_encoding=="UTF-8"&&mb_check_encoding($string,"UTF-8")){
                                        $string=$string;
                                    }
                                    else{
                                        $string=utf8_encode($string);
                                    }
                                    if(!empty($string)){
                                        if(empty($val)){
                                            $val=$nb+'';
                                            $nb++;
                                        }
                                        $description[$val]=$string;
                                    }
                                }
                                $tax['description']=$description;
                                $taxon=$dm->getRepository('PlantnetDataBundle:Taxon')
                                    ->findOneBy(array(
                                        'module.id'=>$module->getId(),
                                        'identifier'=>$tax['identifier']
                                    ));
                                if($taxon){
                                    $i++;
                                    $taxon->setAttributes($tax['description']);
                                    $dm->persist($taxon);
                                    if($i>=$batch_size){
                                        $dm->flush();
                                        $i=0;
                                        $dm->clear();
                                        gc_collect_cycles();
                                        $module=$dm->getRepository('PlantnetDataBundle:Module')
                                            ->findOneBy(array(
                                                'id'=>$id_module
                                            ));
                                    }
                                }
                            }
                        }
                        $dm->flush();
                        $dm->clear();
                        gc_collect_cycles();
                        $module=$dm->getRepository('PlantnetDataBundle:Module')
                            ->findOneBy(array(
                                'id'=>$id_module
                            ));
                    }
                    else{
                        $error='CSV file contains some errors...';
                    }
                }
            }
            elseif($action=='undesc'){
                \MongoCursor::$timeout=-1;
                // remove taxa' descriptions
                $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                    ->update()
                    ->multiple(true)
                    ->field('module')->references($module)
                    ->field('attributes')->unsetField()
                    ->getQuery()
                    ->execute();
            }
            $message=$error;
            if(empty($message)){
                $message='Taxa were created successfully.';
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
        }
        $connection=new \MongoClient();
        $db=$connection->$dbname;
        $db->Module->update(array('_id'=>new \MongoId($id_module)),array(
            '$set'=>array(
                'updating'=>false
            )
        ));
    }

    private function data_encode($data)
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