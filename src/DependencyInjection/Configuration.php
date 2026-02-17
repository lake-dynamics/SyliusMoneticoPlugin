<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('lake_dynamics_sylius_monetico');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('failed_payment_redirect_route')
                    ->cannotBeEmpty()
                    ->defaultValue('sylius_shop_order_show')
                ->end()
                ->scalarNode('success_payment_redirect_route')
                    ->cannotBeEmpty()
                    ->defaultValue('sylius_shop_order_thank_you')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
