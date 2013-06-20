<?php

namespace Plantnet\UserBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use FOS\UserBundle\Form\Type\RegistrationFormType as BaseType;

use Symfony\Component\Form\CallbackValidator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;

class RegistrationFormType extends BaseType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        // add your custom field
        $builder->add('dbName','text',array(
            'label'=>'Database name (only letters):',
            'required'=>true
        ));
        $builder->addValidator(new CallbackValidator(function(FormInterface $form){
            $dbName=$form->get('dbName');
            if(!is_null($dbName->getData())){
                if(!ctype_lower($dbName->getData())){
                    $dbName->addError(new FormError("This field is not valid (only letters)"));
                }
                if(strlen($dbName->getData())<3||strlen($dbName->getData())>50){
                    $dbName->addError(new FormError("This field must contain 3 to 50 letters"));
                }
            }
            else{
                $dbName->addError(new FormError("This field must not be empty"));
            }
        }));
    }

    public function getName()
    {
        return 'plantnet_user_registration';
    }
}