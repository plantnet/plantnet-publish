<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class PropertiesOtherType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder,array $options)
	{
		$builder
			->add('name')
			->add('type','choice',array(
				'choices'=>array(
					'idparent'=>'Parent'
				),
				'multiple'=>false,
				'required'=>false
			))
			->add('details','checkbox',array(
				'required'=>false
			))
			->add('search','checkbox',array(
				'required'=>false
			))
			->add('sortorder','integer',array(
				'required'=>false,
				'attr'=>array('min'=>1)
			))
		;
	}

	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class'=>'Plantnet\DataBundle\Document\Property',
        ));
    }

	public function getDefaultOptions(array $options)
	{
		return array(
			'data_class'=>'Plantnet\DataBundle\Document\Property',
		);
	}

	public function getName()
	{
		return 'properties';
	}
}