<?php
namespace Coolminds\PayByInvoice\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\InvoicingPlugin\Entity\InvoiceInterface;
use Sylius\InvoicingPlugin\Email\InvoiceEmailSenderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SendOnInvoiceInvoiceEmailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly InvoiceEmailSenderInterface $invoiceEmailSender,
        private readonly ManagerRegistry $doctrine,
        private readonly string $onInvoicePaymentCode, // bv. "on_invoice"
        private readonly bool $preventDuplicateMail = true // optioneel
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.order.post_complete' => 'onOrderComplete',
        ];
    }

    public function onOrderComplete(object $event): void
    {
        $order = $this->extractOrder($event);
        if (!$order instanceof OrderInterface) {
            return;
        }

        $payment = $order->getLastPayment() ?? ($order->getPayments()->first() ?: null);
        $code = $payment?->getMethod()?->getCode();
        if ($code !== $this->onInvoicePaymentCode) {
            return;
        }

        // Eenvoudige (optionele) guard tegen duplicaten binnen dezelfde request-lifecycle
        if ($this->preventDuplicateMail && $this->runtimeAlreadySent($order)) {
            return;
        }

        $invoice = $this->findInvoiceByOrderNumber($order->getNumber());
        if (!$invoice instanceof InvoiceInterface) {
            return;
        }

        $customerEmail = (string) $order->getCustomer()?->getEmail();
        $this->invoiceEmailSender->sendInvoiceEmail($invoice, $customerEmail);

        $this->markRuntimeSent($order);
    }

    // --- helpers (KISS: geen extra classes; private methods)
    private function extractOrder(object $event): ?OrderInterface
    {
        // Symfony 5 GenericEvent -> getSubject()
        if (method_exists($event, 'getSubject')) {
            $subject = $event->getSubject();
            return $subject instanceof OrderInterface ? $subject : null;
        }

        // Sommige custom events hangen direct de order aan een getter
        if (method_exists($event, 'getOrder') && $event->getOrder() instanceof OrderInterface) {
            return $event->getOrder();
        }

        return null;
    }

    private function findInvoiceByOrderNumber(string $orderNumber): ?InvoiceInterface
    {
        $repo = $this->doctrine->getRepository(InvoiceInterface::class);

        if (method_exists($repo, 'findByOrderNumber')) {
            $found = $repo->findByOrderNumber($orderNumber);
            return \is_array($found) ? ($found[0] ?? null) : null;
        }

        // Fallback op oudere/nieuwere plugin versies
        return $repo->findOneBy(['orderNumber' => $orderNumber]);
    }

    // --- ultra-lichte runtime “duplicate guard” zonder DB mutatie
    private array $sentRuntime = [];
    private function runtimeKey(OrderInterface $order): string
    {
        return $order->getNumber() ?: spl_object_hash($order);
    }
    private function runtimeAlreadySent(OrderInterface $order): bool
    {
        return isset($this->sentRuntime[$this->runtimeKey($order)]);
    }
    private function markRuntimeSent(OrderInterface $order): void
    {
        $this->sentRuntime[$this->runtimeKey($order)] = true;
    }
}
