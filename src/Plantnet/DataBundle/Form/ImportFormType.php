<?php

namespace Plantnet\DataBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;


class ImportFormType extends AbstractType
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
                ->add('properties', 'collection', array(
                    'type' => new Type\PropertiesType(),
                ))
            ;
        }
        elseif($module->getType()=='locality')
        {
            $builder
                ->add('properties', 'collection', array(
                    'type' => new Type\PropertiesLocalityType(),
                ))
            ;
        }
        elseif($module->getType()=='image')
        {
            $builder
                ->add('properties', 'collection', array(
                    'type' => new Type\PropertiesImageType(),
                ))
            ;
        }
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