<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ModulesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $module=$options['data'];
        $builder
            ->add('name')
            ->add('url')
        ;
        if($module->getType()=='text')
        {
            $builder
                ->add('taxonomy', 'checkbox', array(
                    'required'=>false
                ))
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
        return 'module';
    }
}