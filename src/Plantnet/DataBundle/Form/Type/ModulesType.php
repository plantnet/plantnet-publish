<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ModulesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $module=$options['data'];
        $builder
            ->add('name')
        ;
        if($module->getType()=='text')
        {
            $builder
                ->add('description', 'textarea', array(
                    'required'=>false
                ))
                ->add('properties', 'collection', array(
                    'type' => new PropertiesType(),
                ))
            ;
        }
        elseif($module->getType()=='locality')
        {
            $builder
                ->add('properties', 'collection', array(
                    'type' => new PropertiesLocalityType(),
                ))
            ;
        }
        elseif($module->getType()=='image')
        {
            $builder
                ->add('properties', 'collection', array(
                    'type' => new PropertiesImageType(),
                ))
            ;
        }
        else
        {
            $builder
                ->add('properties', 'collection', array(
                    'type' => new PropertiesOtherType(),
                ))
            ;
        }
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Plantnet\DataBundle\Document\Module',
        );
    }

    public function getName()
    {
        return 'module';
    }
}