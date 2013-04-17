<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class PropertiesOtherType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('name')
			->add('type', 'choice',array(
				'choices' => array(
					'idparent' => 'Parent'
				),
				'multiple' => false,
				'required' => false
			))
		;
	}

	public function getDefaultOptions(array $options)
	{
		return array(
			'data_class' => 'Plantnet\DataBundle\Document\Property',
		);
	}

	public function getName()
	{
		return 'properties';
	}
}