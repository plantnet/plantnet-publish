<?php

namespace Plantnet\DataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class PropertiesGlossaryType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('name')
			->add('type', 'choice',array(
				'choices' => array(
					'keyword' => 'Keyword(s)',
					'definition' => 'Definition',
					'file' => 'File'
				),
				'multiple' => false,
				'required' => false
			))
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
		return 'properties';
	}
}