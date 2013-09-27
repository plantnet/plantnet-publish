<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\CallbackValidator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;

class ConfigTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder,array $options)
    {
        $builder
            ->add('template','choice',array(
                'choices'=>$options['templates'],
                'required'=>true
            ))
        ;
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class'=>'Plantnet\DataBundle\Document\Config',
        );
    }

    public function getName()
    {
        return 'config';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $compound=function (Options $options){
            return $options['templates'];
        };
        $resolver->setDefaults(array(
            'templates'=>$compound,
            'data_class'=>'Plantnet\DataBundle\Document\Config',
        ));
    }
}