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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id=$input->getArgument('collection');
        $idmodule=$input->getArgument('module');
        $dbname=$input->getArgument('dbname');
        $usermail=$input->getArgument('usermail');
        if($id&&$idmodule&&$dbname&&$usermail)
        {
            $error='';
            $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($dbname);
            $configuration = $dm->getConnection()->getConfiguration();
            $configuration->setLoggerCallable(null);
            $module = $dm->getRepository('PlantnetDataBundle:Module')
                ->find($idmodule);
            if (!$module) {
                $error='Unable to find Module entity.';
            }
            if ($module->getType()=='text') {
                $error='Module entity: Wrong type.';
            }
            if(empty($error))
            {
                /*
                 * Open the uploaded csv
                 */
                $csvfile = __DIR__.'/../Resources/uploads/'.$module->getCollection()->getAlias().'/'.$module->getName_fname().'.csv';
                $handle = fopen($csvfile, "r");
                /*
                 * Get the module properties
                 */
                $columns=fgetcsv($handle,0,";");
                $fields = array();
                $attributes = $module->getProperties();
                foreach($attributes as $field){
                    $fields[] = $field;
                }
                /*
                 * Initialise the metrics
                 */
                //echo "Memory usage before: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
                $s = microtime(true);
                $batchSize = 500;
                $rowCount = '';
                while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                    $num = count($data);
                    $rowCount++;
                    if ($module->getType() == 'image'){
                        $image = new Image();
                        $attributes = array();
                        for($c=0; $c < $num; $c++)
                        {
                            $value = $this->data_encode($data[$c]);
                            $attributes[$fields[$c]->getName()] = $value;
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
                        if($module->getParent())
                        {
                            $parent_q=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                                ->field('module.id')->equals($module->getParent()->getId())
                                ->field('identifier')->equals($image->getIdparent())
                                ->getQuery()
                                ->execute();
                            foreach($parent_q as $p)
                            {
                                $parent=$p;
                            }
                        }
                        if($parent)
                        {
                            $image->setPlantunit($parent);
                            $dm->persist($image);
                        }
                        else
                        {
                            $plantunit=new Plantunit();
                            $plantunit->setModule($module);
                            $plantunit->setAttributes($attributes);
                            $plantunit->setIdentifier($image->getIdentifier());
                            $dm->persist($plantunit);
                            $image->setPlantunit($plantunit);
                            $dm->persist($image);
                        }
                    }elseif ($module->getType() == 'locality'){
                        $location = new Location();
                        $coordinates=new Coordinates();
                        $attributes = array();
                        for($c=0; $c < $num; $c++)
                        {
                            $value = $this->data_encode($data[$c]);
                            $attributes[$fields[$c]->getName()] = $value;
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
                        if($module->getParent())
                        {
                            $parent_q=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
                                ->field('module.id')->equals($module->getParent()->getId())
                                ->field('identifier')->equals($location->getIdparent())
                                ->getQuery()
                                ->execute();
                            foreach($parent_q as $p)
                            {
                                $parent=$p;
                            }
                        }
                        if($parent)
                        {
                            $location->setPlantunit($parent);
                            $dm->persist($location);
                        }
                        else
                        {
                            $plantunit=new Plantunit();
                            $plantunit->setModule($module);
                            $plantunit->setAttributes($attributes);
                            $plantunit->setIdentifier($location->getIdentifier());
                            $plantunit->setTitle1($location->getTitle1());
                            $plantunit->setTitle2($location->getTitle2());
                            $dm->persist($plantunit);
                            $location->setPlantunit($plantunit);
                            $dm->persist($location);
                        }
                    }
                    if (($rowCount % $batchSize) == 0) {
                        $dm->flush();
                        $dm->clear();
                        $module = $dm->getRepository('PlantnetDataBundle:Module')->find($idmodule);
                    }
                }
                $module->setNbrows($rowCount);
                $dm->persist($module);
                $dm->flush();
                $dm->clear();
                //echo "Memory usage after: " . (memory_get_usage() / 1024) . " KB" . PHP_EOL;
                $e = microtime(true);
                echo ' Inserted '.$rowCount.' objects in ' . ($e - $s) . ' seconds' . PHP_EOL;
                fclose($handle);
                if(file_exists($csvfile))
                {
                    unlink($csvfile);
                }
                $message='Importation Success: '.$rowCount.' objects imported in '.($e-$s).' seconds';
                /*
                // Récupération du mailer service.
                $container=$this->getContainer();
                $mailer=$container->get('mailer');
                // Création de l'e-mail : le service mailer utilise SwiftMailer, donc nous créons une instance de Swift_Message.
                $message=\Swift_Message::newInstance()
                ->setSubject('Importation success')
                ->setFrom('support@plantnet-project.org')
                ->setTo($usermail)
                ->setBody('Your data for the module were imported');
                // Retour au service mailer, nous utilisons sa méthode « send() » pour envoyer notre $message.
                $mailer->send($message);
                */
            }
            else
            {
                $message=$error;
            }
            mail($usermail,'Pl@ntnet - Publish',$message);
        }
    }

    protected function data_encode($data)
    {
        $data_encoding = mb_detect_encoding($data) ;
        if($data_encoding == "UTF-8" && mb_check_encoding($data,"UTF-8")){
            $format = $data;
        }else {
            $format = utf8_encode($data);
        }
        return $format;
    }
}