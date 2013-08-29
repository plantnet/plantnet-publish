<?php

namespace Plantnet\UserBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use FOS\UserBundle\Form\Type\RegistrationFormType as BaseType;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;

class RegistrationFormType extends BaseType
{
    public function buildForm(FormBuilderInterface $builder,array $options)
    {
        parent::buildForm($builder, $options);
        // custom fields
        $builder->add('super','checkbox',array(
            'label'=>'Looking for Super Admin account:',
            'required'=>false
        ));
        $builder->add('dbNameUq','text',array(
            'label'=>'Database name (only letters):',
            'required'=>false
        ));
        $builder->add('defaultlanguage','language',array(
            'label'=>'Default language:',
            'required'=>false
        ));
        // custom validation
        $extraValidator=function(FormEvent $event){
            $form=$event->getForm();
            $dbNameUq=$form->get('dbNameUq');
            $defaultlanguage=$form->get('defaultlanguage');
            $super=$form->get('super')->getData();
            if(!$super){
                if(!is_null($dbNameUq->getData())){
                    if(!ctype_lower($dbNameUq->getData())){
                        $dbNameUq->addError(new FormError("This field is not valid (only letters)"));
                    }
                    if(strlen($dbNameUq->getData())<3||strlen($dbNameUq->getData())>50){
                        $dbNameUq->addError(new FormError("This field must contain 3 to 50 letters"));
                    }
                }
                else{
                    $dbNameUq->addError(new FormError("This field must not be empty"));
                }
                if(is_null($defaultlanguage->getData())){
                    $defaultlanguage->addError(new FormError("This field must not be empty"));
                }
            }
        };
        $builder->addEventListener(FormEvents::POST_BIND,$extraValidator);
    }

    public function getName()
    {
        return 'plantnet_user_registration';
    }
}