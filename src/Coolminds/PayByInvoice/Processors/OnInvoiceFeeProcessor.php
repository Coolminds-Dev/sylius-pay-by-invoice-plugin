<?php

namespace Coolminds\PayByInvoice\Processors;

use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface as CorePaymentMethodInterface;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\AdjustmentInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OnInvoiceFeeProcessor implements OrderProcessorInterface {
    public function __construct(
        private readonly string $onInvoicePaymentCode,
        private readonly float $feePercentage,
        private readonly AdjustmentFactoryInterface $adjustmentFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function process(OrderInterface $order): void
    {
        $this->processWithOverride($order, null);
    }


    private function isAfterPaymentSelection(CoreOrderInterface $order): bool
    {
        $state = method_exists($order, 'getCheckoutState') ? (string)$order->getCheckoutState() : '';
        return in_array($state, ['payment_selected', 'completed'], true);
    }

    private function removeOwnFees(CoreOrderInterface $order): void
    {
        $existing = $order->getAdjustments('on_invoice_fee');
        $count = count($existing);
        if ($count > 0) {
            $order->removeAdjustments('on_invoice_fee');
        }

        $fallback = 0;
        foreach ($order->getAdjustments() as $adj) {
            if (($adj->getDetails()['on_invoice_fee'] ?? false) === true) {
                $order->removeAdjustment($adj);
                $fallback++;
            }
        }
    }

    private function formatPercent(float $value): string
    {
        $f = number_format($value, 2, ',', '.');
        return rtrim(rtrim($f, '0'), ',');
    }

    public function processWithOverride(OrderInterface $order, ?CorePaymentMethodInterface $override = null): void
    {
        if (!$order instanceof CoreOrderInterface) {
            return;
        }

        if (!$this->isAfterPaymentSelection($order) && $override === null) {
            $this->removeOwnFees($order);
            return;
        }

        $effectiveMethod = $override ?: $order->getLastPayment()?->getMethod();
        $code = $effectiveMethod?->getCode();

        if (null === $effectiveMethod || $code !== $this->onInvoicePaymentCode) {
            $this->removeOwnFees($order);
            return;
        }

        $this->removeOwnFees($order);

        $itemsTotal = $order->getItemsTotal();
        $feeAmount = (int)\round($itemsTotal * ($this->feePercentage / 100));

        if ($feeAmount <= 0) {
            return;
        }

        $locale = method_exists($order, 'getLocaleCode') ? $order->getLocaleCode() : 'nl_NL';
        $previousLocale = null;

        if (method_exists($this->translator, 'setLocale')) {
            $previousLocale = $this->translator->getLocale();
            $this->translator->setLocale($locale);
        }

        $label = $this->translator->trans('on_invoice.fee_label_with_percent', [
            '%fee%' => $this->formatPercent($this->feePercentage),
        ]);

        if ($previousLocale !== null) {
            $this->translator->setLocale($previousLocale);
        }

        /** @var AdjustmentInterface $adjustment */
        $adjustment = $this->adjustmentFactory->createWithData('on_invoice_fee', $label, $feeAmount);
        if (method_exists($adjustment, 'setNeutral')) {
            $adjustment->setNeutral(false);
        }
        $adjustment->setDetails(['on_invoice_fee' => true]); // voor fallback clean

        $order->addAdjustment($adjustment);
    }
}
