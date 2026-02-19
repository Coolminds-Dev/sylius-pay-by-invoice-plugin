<?php

namespace Coolminds\PayByInvoice\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('coolminds_pay_by_invoice');

        $root = $treeBuilder->getRootNode();
        $root
            ->children()
            ->scalarNode('payment_code')
            ->info('Code of the on-invoice payment method')
            ->defaultValue('on_invoice')
            ->end()
            ->scalarNode('fee_percentage')
            ->info('Percentage surcharge for on-invoice payments')
            ->defaultValue(5.0)
            ->end()
            ->scalarNode('group_code')
            ->info('Customer group code allowed to use on-invoice payments')
            ->defaultValue('betalen_op_factuur')
            ->end()
            ->booleanNode('display_in_description')
            ->info('Whether to display the fee percentage in the payment method label')
            ->defaultTrue()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
