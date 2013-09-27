<?php

namespace Plantnet\DataBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ImportGlossaryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('properties','collection',array(
                'type'=>new Type\PropertiesGlossaryType(),
            ))
        ;
    }

    public function getName()
    {
        return 'glossary';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class'=>'Plantnet\DataBundle\Document\Glossary',
        ));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class'=>'Plantnet\DataBundle\Document\Glossary',
        );
    }
}