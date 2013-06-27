<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class PropertiesLangType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('name')
		;
	}

	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Plantnet\DataBundle\Document\Property',
        ));
    }

	public function getDefaultOptions(array $options)
	{
		return array(
			'data_class' => 'Plantnet\DataBundle\Document\Property',
		);
	}

	public function getName()
	{
		return 'propertieslang';
	}
}