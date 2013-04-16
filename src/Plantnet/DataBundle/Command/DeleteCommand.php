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
    Plantnet\DataBundle\Document\Coordinates;

ini_set('memory_limit', '-1');

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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type=$input->getArgument('type');
        $id=$input->getArgument('id');
        $dbname=$input->getArgument('dbname');
        if($type&&$id&&($type=='collection'||$type=='module')&&$dbname)
        {
            if($type=='module')
            {
                $this->delete_module($dbname,$id);
            }
            elseif($type=='collection')
            {
                $this->delete_collection($dbname,$id);
            }
        }
    }

    private function delete_module($dbname,$id)
    {
        $error='';
        $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($dbname);
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
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
        $children=$module->getChildren();
        if(count($children))
        {
            foreach($children as $child)
            {
                $this->delete_module($dbname,$child->getId());
            }
        }
        /*
        * Remove csv file
        */
        $csvfile=__DIR__.'/../Resources/uploads/'.$collection->getAlias().'/'.$module->getName_fname().'.csv';
        if(file_exists($csvfile))
        {
            unlink($csvfile);
        }
        /*
        * Remove upload directory
        */
        $dir=$module->getUploaddir();
        if($dir)
        {
            $dir=__DIR__.'/../../../../web/uploads/'.$dir;
            if(file_exists($dir)&&is_dir($dir))
            {
                $files=scandir($dir);
                foreach($files as $file)
                {
                    if($file!='.'&&$file!='..')
                    {
                        unlink($dir.'/'.$file);
                    }
                }
                rmdir($dir);
            }
        }
        /*
        * Remove Module + Cascade
        */
        $dm->remove($module);
        $dm->flush();
    }

    private function delete_collection($dbname,$id)
    {
        $error='';
        $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($dbname);
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
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
        if(count($modules))
        {
            foreach($modules as $module)
            {
                $this->delete_module($dbname,$module->getId());
            }
        }
        /*
        * Remove csv directory (and files)
        */
        $dir=__DIR__.'/../Resources/uploads/'.$collection->getAlias();
        if(file_exists($dir)&&is_dir($dir))
        {
            $files=scandir($dir);
            foreach($files as $file)
            {
                if($file!='.'&&$file!='..')
                {
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
    }
}