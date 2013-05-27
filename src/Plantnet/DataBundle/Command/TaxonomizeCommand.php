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

ini_set('memory_limit', '-1');

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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id=$input->getArgument('id');
        $dbname=$input->getArgument('dbname');
        if($id&&$dbname)
        {
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
        ksort($taxo);
        // chargement des punits du module
        $ids_punit=array();
        $punits=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->hydrate(false)
            ->select('_id')
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
        foreach($punits as $id)
        {
            $ids_punit[]=$id['_id']->{'$id'};
        }
        unset($punits);
        $batch_size=100;
        $size=0;
        foreach($ids_punit as $id_punit)
        {
            $size++;
            $punit=$dm->getRepository('PlantnetDataBundle:Plantunit')
                ->findOneBy(array(
                    'id'=>$id_punit
                ));
            if($punit)
            {
                $last_taxon=null;
                $attributes=$punit->getAttributes();
                end($taxo);
                $last_level=key($taxo);
                reset($taxo);
                foreach($taxo as $level=>$tab)
                {
                    $attr_id=$tab[0];
                    $attr_lbl=$tab[1];
                    foreach($attributes as $id_attr=>$attr)
                    {
                        if($id_attr==$attr_id&&!empty($attr))
                        {
                            $taxon=$dm->getRepository('PlantnetDataBundle:Taxon')
                                ->findOneBy(array(
                                    'module.id'=>$module->getId(),
                                    'name'=>$attr,
                                    'level'=>$level
                                ));
                            if($taxon)
                            {
                                $taxon->setNbpunits($taxon->getNbpunits()+1);
                                if($level==$last_level){
                                    $punit->setTaxon($taxon);
                                    $dm->persist($punit);
                                }
                            }
                            else
                            {
                                $taxon=new Taxon();
                                $taxon->setName($attr);
                                $taxon->setLabel($attr_lbl);
                                $taxon->setLevel($level);
                                $taxon->setModule($module);
                                $taxon->setNbpunits(1);
                                if($level==$last_level){
                                    $punit->setTaxon($taxon);
                                    $dm->persist($punit);
                                }
                                if($last_taxon){
                                    $taxon->setParent($last_taxon);
                                    if($last_taxon->getHaschildren()!=true){
                                        $last_taxon->setHaschildren(true);
                                        $dm->persist($last_taxon);
                                    }
                                }
                                echo $taxon->getName()."\n";
                            }
                            if($punit->getHasimages()===true){
                                $taxon->setHasimages(true);
                            }
                            if($punit->getHaslocations()===true){
                                $taxon->setHaslocations(true);
                            }
                            $dm->persist($taxon);
                            //flush pour avoir un ID ...
                            $dm->flush();
                            $last_taxon=$taxon;
                        }
                    }
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
    }
}