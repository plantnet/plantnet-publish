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

class TestCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('publish:test')
            ->setDescription('cmd test')
            ->addArgument('num',InputArgument::REQUIRED,'Specify the num')
        ;
    }

    protected function execute(InputInterface $input,OutputInterface $output)
    {
        $num=$input->getArgument('num');
        if($num){
            $this->test_module($num);
        }
    }

    private function test_module($num)
    {
        $dir=__DIR__.'/../Resources/uploads/';
        if($num==1){
            phpinfo();
        }
        if($num==2){
            $file=fopen($dir.'test.txt',"w+");
            if($file){
                echo 'test.txt'."\n";
                fclose($file);
            }
            else{
                echo 'error: test.txt'."\n";
            }
        }
        if($num==3){
            if(file_exists($dir.'test.txt')){
                if(unlink($dir.'test.txt')){
                    echo 'rm test.txt'."\n";
                }
                else{
                    echo 'error: test.txt'."\n";
                }
            }
        }
        echo "\n".'------- end -------'."\n";
    }
}