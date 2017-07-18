<?php

namespace AppBundle\Form;

use AppBundle\AppBundle;
use AppBundle\Entity\Otp;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('email', 'email')
                ->add('submit', 'submit');
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
           'data_class'  => 'AppBundle\Entity\Otp'
        ]);
    }

    public function getBlockPrefix()
    {
        return 'app_bundle_email';
    }

    public function getName()
    {
        return 'email';
    }
}