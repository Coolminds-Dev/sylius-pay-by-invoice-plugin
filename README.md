# Coolminds Sylius Pay-By-Invoice Plugin

Adds a configurable “Pay by Invoice” flow to Sylius 2.0 (Symfony 6.4):

- Define a payment method. example "on_invoice"
- Define a customer group example. "pay_by_invoice"

A proccessor wil applie a configurable percentage surcharge when your specific payment method (e.g. on_invoice) is selected.
The form type wil show/hide the payment method based on a your Customer Group during checkout.

Displays in scheckout summary. admin order, Twig hooks + one template override to display the surcharge in Checkout, Admin > Order, and Invoice PDF.

## Requirements

PHP 8.2+

Symfony 6.4.x

Sylius 2.0.x


## Installation
```terminaloutput
Require the plugin
composer require "coolminds/sylius-pay-by-invoice-plugin:*@dev"
```
## Enable the bundle
`config/bundles.php`
```
return [
    // ...
    Coolminds\PayByInvoice\CoolmindsPayByInvoicePlugin::class => ['all' => true],
];
```

## Configure
Create `config/packages/coolminds_pay_by_invoice.yaml`
``` yaml
coolminds_pay_by_invoice:
  fee_percentage: 2.5              # float, e.g. 2.5 = 2.5%
  payment_code: 'on_invoice'       # Sylius PaymentMethod code that triggers the fee
  group_code: 'betalen_op_factuur' # CustomerGroup code allowed to see/use this method
  display_in_description: true     # append "(+X%)" to the payment label in the shop
```

## Clear & warm cache
```
php -d memory_limit=-1 bin/console cache:clear
php -d memory_limit=-1 bin/console cache:warmup
```

## Translations
By deafault translations for NL and EN ar available.

Available keys:
```yaml
on_invoice:
  fee_suffix: "(Note: +%fee%%)"                       # EN
  fee_label: "Surcharge for payment on invoice"
  fee_label_with_percent: "Surcharge for payment on invoice (%fee%%)"
```

## invoice twig template
Include below to your invoice twig templat. This way the fee will be visible on your invoice.

```yaml
{% include '@CoolmindsPayByInvoice/bundles/SyliusInvoicingPlugin/shared/download/_on_invoice_fee.html.twig' 
  with { invoice: invoice } 
%}
```
