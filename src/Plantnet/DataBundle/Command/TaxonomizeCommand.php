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
            ->addArgument('id',InputArgument::REQUIRED,'Specify the ID of the module entity')
            ->addArgument('dbname',InputArgument::REQUIRED,'Specify a database name')
        ;
    }

    protected function execute(InputInterface $input,OutputInterface $output)
    {
        $id=$input->getArgument('id');
        $dbname=$input->getArgument('dbname');
        if($id&&$dbname){
            $this->taxonomize($dbname,$id);
        }
    }

    private function taxonomize($dbname,$id_module)
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
        // suppression des anciens taxons
        $dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->remove()
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
        // suppression des références des anciens taxons
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
        // chargement des données taxo
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
            ksort($taxo);
            end($taxo);
            $last_level=key($taxo);
            reset($taxo);
            // chargement des punits du module
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
                                }
                            }
                        }
                        $punit->addTaxonsref($taxon);
                        $dm->persist($punit);
                        if($punit->getHasimages()===true){
                            $taxon->setHasimages(true);
                            $images=$punit->getImages();
                            foreach($images as $img){
                                $img->addTaxonsref($taxon);
                                $dm->persist($img);
                            }
                        }
                        if($punit->getHaslocations()===true){
                            $taxon->setHaslocations(true);
                            $locations=$punit->getLocations();
                            foreach($locations as $loc){
                                $loc->addTaxonsref($taxon);
                                $dm->persist($loc);
                            }
                        }
                        $dm->persist($taxon);
                        //flush pour avoir un ID ...
                        $dm->flush();
                        $last_taxon=$taxon;
                    }
                }
                if(($size%$batch_size)==0){
                    $dm->clear();
                    $module=$dm->getRepository('PlantnetDataBundle:Module')
                        ->findOneBy(array(
                            'id'=>$id_module
                        ));
                }
            }
            // gestion de la synonymie
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
                }
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
                        $last_non_valid->setNbpunits($last_non_valid->getNbpunits()-$nb_to_switch);
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
                    $module=$dm->getRepository('PlantnetDataBundle:Module')
                        ->findOneBy(array(
                            'id'=>$id_module
                        ));
                }
            }
            $module=$dm->getRepository('PlantnetDataBundle:Module')
                ->findOneBy(array(
                    'id'=>$id_module
                ));
            $module->setUpdating(false);
            $dm->persist($module);
            $dm->flush();
        }
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