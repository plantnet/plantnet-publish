<?php

namespace Plantnet\DataBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;


class ImportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        
        $builder
            ->add('name')
            ->add('type', 'choice',array(
                     'choices'   => array(
                          'text'   => 'Text',
                          'image' => 'Image',
                          'locality'   => 'Localisation')))
            
            ->add('properties', 'collection', array(
                'type'       => new Type\PropertiesType(),
            ))
        ;
    }

    public function getName()
    {
        return 'modules';
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            
            'data_class' => 'Plantnet\DataBundle\Document\Module',
        );
    }

}