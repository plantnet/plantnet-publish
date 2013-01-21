<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class CollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('description', 'textarea', array('required'=>false))
            /*->add('modules', 'collection', array(
                'type'       => new ModulesType(),
                'modifiable' => true,
            ))*/
        
        ;
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Plantnet\DataBundle\Document\Collection',
        );
    }

    public function getName()
    {
        return 'collection';
    }
    
}