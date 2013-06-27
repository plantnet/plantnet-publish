<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ModulesTaxoLangType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $module=$options['data'];
        if($module->getType()=='text')
        {
            $builder
                ->add('properties', 'collection', array(
                    'type' => new PropertiesTaxoLangType(),
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