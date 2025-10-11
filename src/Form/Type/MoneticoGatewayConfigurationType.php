<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class MoneticoGatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tpe', TextType::class, [
                'label' => 'lake_dynamics_sylius_monetico.form.gateway_configuration.tpe',
                'constraints' => [
                    new NotBlank([
                        'message' => 'lake_dynamics_sylius_monetico.form.gateway_configuration.tpe.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('company_id', TextType::class, [
                'label' => 'lake_dynamics_sylius_monetico.form.gateway_configuration.company_id',
                'constraints' => [
                    new NotBlank([
                        'message' => 'lake_dynamics_sylius_monetico.form.gateway_configuration.company_id.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('prod_key', TextType::class, [
                'label' => 'lake_dynamics_sylius_monetico.form.gateway_configuration.prod_key',
                'constraints' => [
                    new NotBlank([
                        'message' => 'lake_dynamics_sylius_monetico.form.gateway_configuration.prod_key.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('use_production', CheckboxType::class, [
                'label' => 'lake_dynamics_sylius_monetico.form.gateway_configuration.use_production',
                'required' => false,
            ])
        ;
    }
}
