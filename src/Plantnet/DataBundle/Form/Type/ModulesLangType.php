<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ModulesLangType extends AbstractType
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
                    'type' => new PropertiesLangType(),
                ))
            ;
        }
        elseif($module->getType()=='locality')
        {
            $builder
                ->add('properties', 'collection', array(
                    'type' => new PropertiesLangType(),
                ))
            ;
        }
        elseif($module->getType()=='image')
        {
            $builder
                ->add('properties', 'collection', array(
                    'type' => new PropertiesLangType(),
                ))
            ;
        }
        else
        {
            $builder
                ->add('properties', 'collection', array(
                    'type' => new PropertiesLangType(),
                ))
            ;
        }
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Plantnet\DataBundle\Document\Module',
        ));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Plantnet\DataBundle\Document\Module',
        );
    }

    public function getName()
    {
        return 'modulelang';
    }
}