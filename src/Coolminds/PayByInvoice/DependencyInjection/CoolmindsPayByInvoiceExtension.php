<?php

namespace Coolminds\PayByInvoice\DependencyInjection;

use ReflectionClass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class CoolmindsPayByInvoiceExtension extends Extension implements PrependExtensionInterface
{
    public function getAlias(): string
    {
        return 'coolminds_pay_by_invoice';
    }
    public function prepend(ContainerBuilder $container): void
    {
        // Haal alle tot nu toe bekende config-fragmenten voor deze bundle op
        $configs = $container->getExtensionConfig($this->getAlias());

        // Verwerk naar één consistente config (incl. defaults)
        $configuration    = new Configuration();
        $processedConfig  = $this->processConfiguration($configuration, $configs);

        // Gebruik LITERAL waarden i.p.v. %parameters% (die bestaan hier nog niet)
        $fee        = $processedConfig['fee_percentage'];
        $code       = $processedConfig['payment_code'];
        $group      = $processedConfig['group_code'];
        $showInDesc = $processedConfig['display_in_description'];

        // 2) Twig namespace registreren (belangrijk!)
        $ref       = new ReflectionClass(\Coolminds\PayByInvoice\CoolmindsPayByInvoicePlugin::class);
        $bundleDir    = \dirname($ref->getFileName());
        $viewsPath = $bundleDir.'/Resources/views';
        $shopOverride = $viewsPath . '/bundles/SyliusShopBundle';

        // 1) Set twig-globals
        $container->prependExtensionConfig('twig', [
            'paths' => [
                $shopOverride => 'SyliusShop',
                $viewsPath => 'CoolmindsPayByInvoice',
            ],
            'globals' => [
                'on_invoice_fee_percentage'         => $fee,
                'on_invoice_payment_code'           => $code,
                'on_invoice_group_code'             => $group,
                'on_invoice_display_in_description' => $showInDesc,
            ],
        ]);

//        // 2) Load Sylius Twig Hooks
        $container->prependExtensionConfig('sylius_twig_hooks', [
            'hooks' => [
                'sylius_shop.checkout.common.sidebar.summary.total' => [
                    'on_invoice_fee' => [
                        'template' => '@CoolmindsPayByInvoice/checkout/sidebar/summary/total/on_invoice_fee.html.twig',
                        'priority' => 250,
                    ],
                ],
                'sylius_invoicing_plugin.shared.download.pdf' => [
                    'on_invoice_fee' => [
                        'template' => '@CoolmindsPayByInvoice/bundles/SyliusInvoicingPlugin/shared/download/_on_invoice_fee.html.twig',
                        'priority' => 150,
                    ],
                ],
                'sylius_admin.order.show.content.sections.summary' => [
                    'on_invoice_fee' => [
                        'template' => '@CoolmindsPayByInvoice/bundles/SyliusAdminBundle/order/show/content/sections/summary/on_invoice_fee.html.twig',
                        'priority' => 50,
                    ],
                ],
            ],
        ]);

        $container->prependExtensionConfig('framework', [
            'translator' => [
                'paths' => [
                    $bundleDir . '/Resources/translations',
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Zet configuratiewaarden als containerparameters
        $container->setParameter('coolminds_pay_by_invoice.payment_code', $config['payment_code']);
        $container->setParameter('coolminds_pay_by_invoice.fee_percentage', $config['fee_percentage']);
        $container->setParameter('coolminds_pay_by_invoice.group_code', $config['group_code']);
        $container->setParameter('coolminds_pay_by_invoice.display_in_description', $config['display_in_description']);

        // Laad services.yaml uit Resources/config
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
