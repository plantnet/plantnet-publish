<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ModulesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder

            ->add('name')
        
            ->add('type', 'choice',array(
                     'choices'   => array(
                          'text'   => 'Text',
                          'image' => 'Image',
                          'locality'   => 'Localisation')))
            /*->add('parent_module', 'entity', array(
                'class'         => 'Plantnet\BotaBundle\Entity\Modules',
                'query_builder' => function ($repository) { return $repository->createQueryBuilder('p')->orderBy('p.name', 'ASC'); },))*/
            //->add('parent_module', 'choice', array($options))
            //->add('attachment', 'file')
            
            ->add('properties', 'collection', array(
                'type'       => new PropertiesType(),
            ))
            /*->add('collection', 'entity', array(
                'class'         => 'Plantnet\BotaBundle\Entity\Collection',
                'query_builder' => function ($repository) { return $repository->createQueryBuilder('p')->orderBy('p.name', 'ASC'); },
            ))*/
             





        ;
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