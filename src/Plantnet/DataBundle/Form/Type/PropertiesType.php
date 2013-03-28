<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class PropertiesType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('name')
			->add('type', 'choice',array(
				'choices' => array(
					'idmodule' => 'Id',
					'title1' => 'Title 1',
					'title2' => 'Title 2'
				),
				'multiple' => false,
				'required' => false
			))
			->add('main', 'checkbox', array(
				'required' => false
			))
			->add('details', 'checkbox', array(
				'required' => false
			))
			->add('search', 'checkbox', array(
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