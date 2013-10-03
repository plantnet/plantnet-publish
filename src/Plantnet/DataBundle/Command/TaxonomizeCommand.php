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
    protected function configure()
    {
        $this
            ->setName('publish:taxon')
            ->setDescription('create taxons entities')
            ->addArgument('action',InputArgument::REQUIRED,'Specify the action (taxo or syn)')
            ->addArgument('id',InputArgument::REQUIRED,'Specify the ID of the module entity')
            ->addArgument('dbname',InputArgument::REQUIRED,'Specify a database name')
            ->addArgument('usermail',InputArgument::REQUIRED,'Specify a user e-mail')
        ;
    }

    protected function execute(InputInterface $input,OutputInterface $output)
    {
        $action=$input->getArgument('action');
        $id=$input->getArgument('id');
        $dbname=$input->getArgument('dbname');
        $usermail=$input->getArgument('usermail');
        if($action&&($action=='taxo'||$action=='syn')){
            if($id&&$dbname&&$usermail){
                $this->taxonomize($action,$dbname,$id,$usermail);
            }
        }
        
    }

    private function populate($db,$dm,$module,$taxo,$level,$filter=array(),$identifier='')
    {
        $cur_filters=array_merge(array('module.$id'=>new \MongoId($module->getId())),$filter);
        $values=$db->command(
            array(
                'distinct'=>'Plantunit',
                'key'=>'attributes.'.$taxo[$level][0],
                'query'=>$cur_filters
            )
        );
        $tab_tax=array();
        foreach($values['values'] as $val){
            $tmp_identifier=$identifier;
            $tab_tax[$val]=array(
                'column'=>$taxo[$level][0],
                'value'=>$val,
                'label'=>$taxo[$level][1],
                'level'=>$level,
                'identifier'=>'',
                'child'=>null
            );
            if($level!=1){
                $tmp_identifier.=' - ';
            }
            $tmp_identifier.=$val;
            $tab_tax[$val]['identifier']=$tmp_identifier;
            if(isset($taxo[$level+1])){
                sleep(0.05);
                $tab_tax[$val]['child']=$this->populate($db,$dm,$module,$taxo,$level+1,array_merge($filter,array('attributes.'.$taxo[$level][0]=>$val)),$tmp_identifier);
            }
        }
        return $tab_tax;
    }

    private function save($dbname,$dm,$module,$taxo,$tab_taxons,$parent=null,$filter=array())
    {
        $connection=new \MongoClient();
        $db=$connection->$dbname;
        foreach($tab_taxons as $tax_name=>$tax_data){
            $taxon=new Taxon();
            $taxon->setIdentifier($tax_data['identifier']);
            $taxon->setName($tax_data['value']);
            $taxon->setLabel($tax_data['label']);
            $taxon->setLevel($tax_data['level']);
            $taxon->setModule($module);
            $taxon->setNbpunits(0);
            $taxon->setIssynonym(false);
            // Punit number
            $cur_filters=array(
                'attributes.'.$taxo[$taxon->getLevel()][0]=>$taxon->getName(),
                'module.$id'=>new \MongoId($module->getId()),
            );
            $cur_filters=array_merge($cur_filters,$filter);
            $nb_punit=$db->Plantunit->find($cur_filters)->count();
            if($nb_punit>0){
                $taxon->setNbpunits($nb_punit);
            }
            // Punit with img number
            $nb_punit_img=$db->Plantunit->find(array_merge($cur_filters,array('hasimages'=>true)))->count();
            if($nb_punit_img>0){
                $taxon->setHasimages(true);
            }
            // Punit with loc number
            $nb_punit_loc=$db->Plantunit->find(array_merge($cur_filters,array('haslocations'=>true)))->count();
            if($nb_punit_loc>0){
                $taxon->setHaslocations(true);
            }
            if($parent){
                $taxon->setParent($parent);
            }
            if(!empty($tax_data['child'])){
                $taxon->setHaschildren(true);
                $dm->persist($taxon);
                $dm->flush();
                $this->save($dbname,$dm,$module,$taxo,$tax_data['child'],$taxon,array_merge($filter,array('attributes.'.$taxo[$taxon->getLevel()][0]=>$taxon->getName())));
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
            $punit_ids=$db->Plantunit->find($cur_filters,array('_id'=>1));
            $punit_ids_array=array();
            foreach($punit_ids as $id=>$data){
                $punit_ids_array[]=$data['_id'];
            }
            $punit_ids=null;
            unset($punit_ids);
            if(count($punit_ids_array)){
                // Set ref Images // Taxon
                $db->Image->update(array('plantunit.$id'=>array('$in'=>$punit_ids_array)),array(
                    '$addToSet'=>array(
                        'taxonsrefs'=>array(
                            '$ref'=>'Taxon',
                            '$id'=>new \MongoId($taxon->getId()),
                            '$db'=>$dbname
                        )
                    )
                ),array('multiple'=>true));
                // Set ref Locations // taxon
                $db->Location->update(array('plantunit.$id'=>array('$in'=>$punit_ids_array)),array(
                    '$addToSet'=>array(
                        'taxonsrefs'=>array(
                            '$ref'=>'Taxon',
                            '$id'=>new \MongoId($taxon->getId()),
                            '$db'=>$dbname
                        )
                    )
                ),array('multiple'=>true));
            }
        }
    }

    private function taxonomize($action,$dbname,$id_module,$usermail)
    {
        echo 'start'."\n";
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
        if($action=='taxo'){
            echo 'start rm'."\n";
            // remove old taxa
            $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
                ->remove()
                ->field('module')->references($module)
                ->getQuery()
                ->execute();
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
            echo 'end rm'."\n";
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
        if(count($taxo)){
            echo 'taxo ok'."\n";
            ksort($taxo);
            $first_level=key($taxo);
            end($taxo);
            $last_level=key($taxo);
            reset($taxo);
            if($action=='taxo'){
                echo 'start populate'."\n";
                //populate
                $s=microtime(true);
                $connection=new \MongoClient();
                $db=$connection->$dbname;
                $tab_tax=$this->populate($db,$dm,$module,$taxo,$first_level);
                $s2=microtime(true);
                echo $s2-$s.' end populate'."\n";
                //save
                echo 'start save'."\n";
                $this->save($dbname,$dm,$module,$taxo,$tab_tax);
                echo 'end save'."\n";
                $dm->clear();
                gc_collect_cycles();
                $module=$dm->getRepository('PlantnetDataBundle:Module')
                    ->findOneBy(array(
                        'id'=>$id_module
                    ));
                $e=microtime(true);
                echo $e-$s.' end save'."\n";
                /*
                // load module's punit
                $ids_punit=array();
                $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                    ->hydrate(false)
                    ->select('_id')
                    ->field('module')->references($module)
                    ->getQuery()
                    ->execute();
                foreach($punits as $id){
                    $ids_punit[]=$id['_id']->{'$id'};
                }
                unset($punits);
                $batch_size=100;
                $size=0;
                foreach($ids_punit as $id_punit){
                    $size++;
                    $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                        ->findOneBy(array(
                            'id'=>$id_punit
                        ));
                    if($punit){
                        $attributes=$punit->getAttributes();
                        $tab_taxo=array();
                        $identifier='';
                        foreach($taxo as $level=>$t){
                            if(isset($attributes[$t[0]])){
                                if(!empty($identifier)){
                                    $identifier.=' - ';
                                }
                                $identifier.=$attributes[$t[0]];
                                $tab_taxo[$level]=array(
                                    $t[1],
                                    $identifier,
                                    $attributes[$t[0]]
                                );
                            }
                        }
                        $last_taxon=null;
                        foreach($tab_taxo as $level=>$data){
                            if(!empty($data[2])){
                                $taxon=$dm->getRepository('PlantnetDataBundle:Taxon')
                                    ->findOneBy(array(
                                        'module.id'=>$module->getId(),
                                        'identifier'=>$data[1],
                                        'level'=>$level
                                    ));
                                if($taxon){
                                    $taxon->setNbpunits($taxon->getNbpunits()+1);
                                    if($level==$last_level){
                                        $punit->setTaxon($taxon);
                                    }
                                }
                                else{
                                    $taxon=new Taxon();
                                    $taxon->setIdentifier($data[1]);
                                    $taxon->setName($data[2]);
                                    $taxon->setLabel($data[0]);
                                    $taxon->setLevel($level);
                                    $taxon->setModule($module);
                                    $taxon->setNbpunits(1);
                                    $taxon->setIssynonym(false);
                                    if($level==$last_level){
                                        $punit->setTaxon($taxon);
                                    }
                                    if($last_taxon){
                                        $taxon->setParent($last_taxon);
                                        if($last_taxon->getHaschildren()!=true){
                                            $last_taxon->setHaschildren(true);
                                            $dm->persist($last_taxon);
                                            $size++;
                                        }
                                    }
                                }
                                $punit->addTaxonsref($taxon);
                                $dm->persist($punit);
                                $size++;
                                if($punit->getHasimages()===true){
                                    $taxon->setHasimages(true);
                                    $images=$punit->getImages();
                                    foreach($images as $img){
                                        $img->addTaxonsref($taxon);
                                        $dm->persist($img);
                                        $size++;
                                    }
                                }
                                if($punit->getHaslocations()===true){
                                    $taxon->setHaslocations(true);
                                    $locations=$punit->getLocations();
                                    foreach($locations as $loc){
                                        $loc->addTaxonsref($taxon);
                                        $dm->persist($loc);
                                        $size++;
                                    }
                                }
                                $dm->persist($taxon);
                                $size++;
                                //flush to get an ID ...
                                $dm->flush();
                                $last_taxon=$taxon;
                            }
                        }
                    }
                    if(($size>=$batch_size)){
                        $dm->clear();
                        gc_collect_cycles();
                        $module=$dm->getRepository('PlantnetDataBundle:Module')
                            ->findOneBy(array(
                                'id'=>$id_module
                            ));
                    }
                }
                */
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
                            if($col==$data[1]){
                                $syns[$level]['col_non_valid']=$key;
                            }
                            if($col!=$data[1]&&strlen($col)>=strlen($data[1])&&substr_count($col,$data[1],0,strlen($data[1]))){
                                $syns[$level]['col_valid']=$key;
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
                        while(($data=fgetcsv($handle,0,';'))!==false){
                            $non_valid_identifier='';
                            $valid_identifier='';
                            $non_valid=array();
                            $valid=array();
                            foreach($syns as $level=>$tab){
                                $string_non_valid=isset($data[$tab['col_non_valid']])?trim($data[$tab['col_non_valid']]):'';
                                $string_valid=isset($data[$tab['col_valid']])?trim($data[$tab['col_valid']]):'';
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
                                $dm->persist($last_non_valid);
                                $dm->persist($last_valid);
                                //
                                $has_images=$last_non_valid->getHasimages();
                                $has_locations=$last_non_valid->getHaslocations();
                                $nb_to_switch=$last_non_valid->getNbpunits();
                                //$last_non_valid->setNbpunits($last_non_valid->getNbpunits()-$nb_to_switch);
                                $dm->persist($last_non_valid);
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
                                $dm->flush();
                            }
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
            /*
            $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($dbname);
            $configuration=$dm->getConnection()->getConfiguration();
            $configuration->setLoggerCallable(null);
            $module=$dm->getRepository('PlantnetDataBundle:Module')
                ->findOneBy(array(
                    'id'=>$id_module
                ));
            $module->setUpdating(false);
            $dm->persist($module);
            $dm->flush();
            */
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
                ))
            ;
            $this->getContainer()->get('mailer')->send($message_mail);
            $spool=$this->getContainer()->get('mailer')->getTransport()->getSpool();
            $transport=$this->getContainer()->get('swiftmailer.transport.real');
            $spool->flushQueue($transport);
        }
        /*
        $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($dbname);
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'id'=>$id_module
            ));
        $module->setUpdating(false);
        $dm->persist($module);
        $dm->flush();
        */
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