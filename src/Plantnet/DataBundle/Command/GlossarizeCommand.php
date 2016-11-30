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
    Plantnet\DataBundle\Document\Glossary,
    Plantnet\DataBundle\Document\Definition;

ini_set('memory_limit','-1');

class GlossarizeCommand extends ContainerAwareCommand
{

    function mylog($data,$data2=null,$data3=null){
        if( $data != null){
            $this->get('ladybug')->log(func_get_args());
        }
    }

    protected function configure()
    {
        $this
            ->setName('publish:glossary')
            ->setDescription('update glossary entity')
            ->addArgument('action',InputArgument::REQUIRED,'Specify the action (syn or unsyn)')
            ->addArgument('id',InputArgument::REQUIRED,'Specify the ID of the glossary entity')
            ->addArgument('dbname',InputArgument::REQUIRED,'Specify a database name')
            ->addArgument('usermail',InputArgument::REQUIRED,'Specify a user e-mail')
        ;
    }

    protected function execute(InputInterface $input,OutputInterface $output)
    {
        $actions=array(
            'syn',
            'unsyn'
        );
        $action=$input->getArgument('action');
        $id=$input->getArgument('id');
        $dbname=$input->getArgument('dbname');
        $usermail=$input->getArgument('usermail');
        if($action&&in_array($action,$actions)){
            if($id&&$dbname&&$usermail){
                $this->glossarize($action,$dbname,$id,$usermail);
            }
        }
    }

    private function glossarize($action,$dbname,$id_glossary,$usermail)
    {
        $error='';
        $dm=$this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($dbname);
        $configuration=$dm->getConnection()->getConfiguration();
        $configuration->setLoggerCallable(null);
        $glossary=$dm->getRepository('PlantnetDataBundle:Glossary')
            ->findOneBy(array(
                'id'=>$id_glossary
            ));
        if(!$glossary){
            $error='Unable to find Glossary entity.';
        }
        $collection=$glossary->getCollection();
        if(!$collection){
            $error='Unable to find Glossary entity.';
        }
        //
        if($action=='syn'){
            $csv=__DIR__.'/../Resources/uploads/'.$collection->getAlias().'/glossary_syn.csv';
            if(file_exists($csv)){
                $handle=fopen($csv,"r");
                $field=fgetcsv($handle,0,";");
                $csv_error=false;
                if(!isset($field[0])||empty($field[0])){
                    $csv_error=true;
                }
                if(!isset($field[1])||empty($field[1])){
                    $csv_error=true;
                }
                if(!$csv_error){
                    $i=0;
                    $batch_size=100;
                    \MongoCursor::$timeout=-1;
                    while(($data=fgetcsv($handle,0,';'))!==false){
                        $def_word=isset($data[0])?trim($data[0]):'';
                        $cur_encoding=mb_detect_encoding($def_word);
                        if($cur_encoding=="UTF-8"&&mb_check_encoding($def_word,"UTF-8")){
                            $def_word=$def_word;
                        }
                        else{
                            $def_word=utf8_encode($def_word);
                        }
                        $syn_word=isset($data[1])?trim($data[1]):'';
                        $cur_encoding=mb_detect_encoding($syn_word);
                        if($cur_encoding=="UTF-8"&&mb_check_encoding($syn_word,"UTF-8")){
                            $syn_word=$syn_word;
                        }
                        else{
                            $syn_word=utf8_encode($syn_word);
                        }
                        if($def_word&&$syn_word){
                            $definition=$dm->getRepository('PlantnetDataBundle:Definition')
                                ->findOneBy(array(
                                    'glossary.id'=>$glossary->getId(),
                                    'name'=>$def_word
                                ));
                            if($definition){
                                $i++;
                                if(!$definition->getHaschildren()){
                                    $definition->setHaschildren(true);
                                    $dm->persist($definition);
                                }
                                $synonym=new Definition();
                                $synonym->setGlossary($glossary);
                                $synonym->setParent($definition);
                                $synonym->setName($syn_word);
                                $synonym->setDisplayedname($syn_word);
                                $synonym->setDefinition($definition->getDefinition());
                                $synonym->setPath($definition->getPath());
                                $dm->persist($synonym);
                                if($i>=$batch_size){
                                    $dm->flush();
                                    $i=0;
                                    $dm->clear();
                                    gc_collect_cycles();
                                    $glossary=$dm->getRepository('PlantnetDataBundle:Glossary')
                                        ->findOneBy(array(
                                            'id'=>$id_glossary
                                        ));
                                }
                            }
                        }
                    }
                    $dm->flush();
                    $dm->clear();
                    gc_collect_cycles();
                    $glossary=$dm->getRepository('PlantnetDataBundle:Glossary')
                        ->findOneBy(array(
                            'id'=>$id_glossary
                        ));
                }
                else{
                    $error='Error in Glossary Syn file.';
                }
            }
            else{
                $error='Unable to find Glossary Syn file.';
            }
            $message=$error;
            if(empty($message)){
                $message='Synonyms were created successfully.';
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
        elseif($action=='unsyn'){
            $dm->createQueryBuilder('PlantnetDataBundle:Definition')
                ->remove()
                ->field('glossary')->references($glossary)
                ->field('parent')->notEqual(null)
                ->getQuery()
                ->execute();
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