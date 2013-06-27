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

class TaxonomizeLangCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('publish:taxon_lang')
            ->setDescription('translate taxons entities (labels)')
            ->addArgument('id',InputArgument::REQUIRED,'Specify the ID of the module entity')
            ->addArgument('lang',InputArgument::REQUIRED,'Specify the language of the module entity')
            ->addArgument('dbname',InputArgument::REQUIRED,'Specify a database name')
        ;
    }

    protected function execute(InputInterface $input,OutputInterface $output)
    {
        $id=$input->getArgument('id');
        $lang=$input->getArgument('lang');
        $dbname=$input->getArgument('dbname');
        if($id&&$lang&&$dbname){
            $this->taxonomize($dbname,$id,$lang);
        }
    }

    private function taxonomize($dbname,$id_module,$lang)
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
        $module->setTranslatableLocale('z'.$lang);
        $dm->refresh($module);
        $properties=$module->getProperties();
        foreach($properties as &$property){
            $property->setTranslatableLocale('z'.$lang);
            $dm->refresh($property);
        }
        // chargement des données taxo
        $taxo=array();
        $fields=$properties;
        foreach($fields as $field){
            if($field->getTaxolevel()){
                $taxo[$field->getTaxolevel()]=$field->getTaxolabel();
            }
        }
        ksort($taxo);
        // mise à jour des labels des Taxons
        $taxons=$dm->createQueryBuilder('PlantnetDataBundle:Taxon')
            ->field('module')->references($module)
            ->getQuery()
            ->execute();
        foreach($taxons as $taxon){
            $taxon->setTranslatableLocale('z'.$lang);
            $dm->refresh($taxon);
            if($taxon->getLevel()){
                $taxon->setLabel($taxo[$taxon->getLevel()]);
                $dm->persist($taxon);
                $dm->flush();
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