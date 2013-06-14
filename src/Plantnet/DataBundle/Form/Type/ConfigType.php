<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\CallbackValidator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $languages=array();
        foreach($options['languages'] as $language){
            $languages[$language]=$language;
        }
        $builder
            ->add('defaultlanguage', 'choice', array(
                'label'=>'Default language',
                'required'=>true,
                'expanded'=>true,
                'choices'=>array(
                    $languages
                )
            ))
            ->add('availablelanguages', 'choice', array(
                'label'=>'Available languages',
                'required'=>true,
                'expanded'=>true,
                'multiple'=>true,
                'choices'=>array(
                    $languages
                )
            ))
            // ->add('availablelanguages', 'text', array('required'=>true))
        ;
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Plantnet\DataBundle\Document\Config',
        );
    }

    public function getName()
    {
        return 'config';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $compound = function (Options $options) {
            return $options['languages'];
        };
        $resolver->setDefaults(array(
            'languages' => $compound,
            'data_class' => 'Plantnet\DataBundle\Document\Config',
        ));
    }
}