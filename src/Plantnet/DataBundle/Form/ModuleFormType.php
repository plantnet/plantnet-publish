<?php

namespace Plantnet\DataBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\CallbackValidator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;

class ModuleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $module=$options['data'];
        if($module->getType()=='submodule')
        {
            $builder
                ->add('name')
                ->add('url')
                ->add('type', 'choice',array(
                    'choices' => array(
                        'image' => 'Image',
                        'locality' => 'Locality',
                        'other' => 'Other'
                    ),
                    'multiple' => false
                ))
                ->add('parent', 'choice',array(
                    'mapped' => false,
                    'choices' => $options['idparent'],
                    'required'=>true
                ))
                ->add('file', 'file')
            ;
        }
        else
        {
            $builder
                ->add('name')
                ->add('url')
                ->add('taxonomy', 'checkbox', array(
                    'required'=>false
                ))
                ->add('description', 'textarea', array(
                    'required'=>false
                ))
                ->add('type', 'hidden',array(
                    'data' => 'text'
                ))
                ->add('parent', 'hidden',array(
                    'data' => ''
                ))
                ->add('file', 'file')
            ;
        }
    }

    public function getName()
    {
        return 'modules';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $compound = function (Options $options) {
            return $options['idparent'];
        };
        $resolver->setDefaults(array(
            'idparent' => $compound,
            'data_class' => 'Plantnet\DataBundle\Document\Module',
        ));
    }
}