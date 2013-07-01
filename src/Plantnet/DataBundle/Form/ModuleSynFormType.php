<?php

namespace Plantnet\DataBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\CallbackValidator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;

class ModuleSynFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $module=$options['data'];
        if($module->getType()=='text')
        {
            $builder->add('synfile', 'file');
        }
    }

    public function getName()
    {
        return 'modules';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Plantnet\DataBundle\Document\Module',
        ));
    }
}