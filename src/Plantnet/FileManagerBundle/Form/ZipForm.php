<?php

namespace Plantnet\FileManagerBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ZipForm extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder,array $options)
    {
        $builder->add('zipFile','file',array('label'=>'Zip file (.zip) - Max file size: 300M'));
    }
    
    public function getName()
    {
    	$namespace=explode('\\',get_class($this));
    	return end($namespace);
    }
}