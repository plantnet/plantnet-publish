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

        $builder
            ->add('name')
            ->add('type', 'choice',array(
                     'choices'   => array(
                          'text'   => 'Text',
                          'image' => 'Image',
                          'locality'   => 'Localisation'), 'multiple'  => false))

            //->add('parent', 'choice', array('choices' => $options['parent'],'required'=>false))
            //->add('parent')
            ->add("parent",
                "choice",
                array(
                    "property_path" => false,
                    'choices' => $options['idparent'],
                    'required'=>false
                ))

            ->add('file', 'file')
        ;

        

    }

    public function getName()
    {
        return 'modules';
    }

    /*public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $compound = function (Options $options) {
            return $options['expanded'];
        };

        $resolver->setDefaults(array(
            'compound' => $compound,
        ));
    }*/

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