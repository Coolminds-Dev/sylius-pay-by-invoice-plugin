<?php
declare(strict_types=1);

namespace Coolminds\PayByInvoice\Form\Extension;

use Sylius\Bundle\PaymentBundle\Form\Type\PaymentMethodChoiceType;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PaymentMethodChoiceTypeExtension extends AbstractTypeExtension {
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly LocaleContextInterface $localeContext,
        private readonly float $onInvoiceFeePercentage,
        private readonly bool $onInvoiceDisplayInDescription,
        private readonly string $onInvoiceGroupCode,
        private readonly TranslatorInterface $translator
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [PaymentMethodChoiceType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {

        // Normaliseer 'choice_filter' (in jouw stack 1-arg callable)
        $resolver->setNormalizer('choice_filter', function (Options $options, $existingFilter) {
            $request = $this->requestStack->getCurrentRequest();
            $route = $request?->attributes->get('_route', '');
            $isShop = \is_string($route) && str_starts_with($route, 'sylius_shop_');

            // Is user in groep 'on_invoice'?
            $user = $this->security->getUser();
            $isOnInvoiceUser = false;
            if ($user && method_exists($user, 'getGroups')) {
                foreach ($user->getGroups() as $group) {
                    if (method_exists($group, 'getCode') && $group->getCode() === $this->onInvoiceGroupCode) {
                        $isOnInvoiceUser = true;
                        break;
                    }
                }
            }

            $callExistingFilter = static function ($filter, $choice): bool {
                if (!\is_callable($filter)) {
                    return true;
                }
                try {
                    // Probeer 1 arg
                    $result = $filter($choice);
                } catch (\ArgumentCountError|\TypeError) {
                    try {
                        // Probeer 2 args
                        $result = $filter($choice, null);
                    } catch (\ArgumentCountError|\TypeError) {
                        // Probeer 3 args
                        $result = $filter($choice, null, null);
                    }
                }
                return (bool)$result;
            };

            return function ($choice) use ($existingFilter, $isShop, $isOnInvoiceUser, $callExistingFilter): bool {
                if (!$callExistingFilter($existingFilter, $choice)) {
                    return false;
                }

                if (!$isShop) {
                    return true;
                }

                if ($choice instanceof PaymentMethodInterface) {
                    if ($choice->getCode() === $this->onInvoiceGroupCode && !$isOnInvoiceUser) {
                        return false;
                    }
                }

                return true;
            };
        });

        $this->setLabel($resolver, $this->onInvoiceDisplayInDescription);
    }

    /**
     * true  => NIET in titel injecteren
     * false => WEL
     *
     * @param OptionsResolver $resolver
     * @param bool|null $addToDescription
     * @return void
     */
    private function setLabel(OptionsResolver $resolver, bool $addToDescription): void
    {
        if ($addToDescription) {
            return;
        }

        $resolver->setDefault('label_html', false);

        $resolver->setNormalizer('choice_label', function (Options $options, $existingLabel) {
            return function ($choice) use ($existingLabel) {
                /** 1) Determine base label via existing config **/
                $base = '';
                if (\is_callable($existingLabel)) {
                    $base = (string) $existingLabel($choice);
                } elseif (\is_string($existingLabel) && $existingLabel !== '') {
                    $base = $existingLabel;
                }
                /** 2) As default (name), retrieve locale-aware name **/
                if ($base === 'name' && $choice instanceof PaymentMethodInterface) {
                    $base = $this->getPaymentMethodName($choice);
                } elseif ($base === '') {
                    $base = (string) $choice;
                }

                /** 3) Add suffix for on_invoice **/
                if ($choice instanceof PaymentMethodInterface && $choice->getCode() === 'on_invoice') {
                    $suffix = ' ' . $this->translator->trans('on_invoice.fee_suffix', [
                            '%fee%' => $this->formatPercent($this->onInvoiceFeePercentage),
                        ]);
                    return $base.$suffix;
                }

                return $base;
            };
        });
    }
    private function getPaymentMethodName(PaymentMethodInterface $method): string
    {

        $locale = $this->requestStack->getCurrentRequest()?->getLocale()
            ?? $this->localeContext->getLocaleCode()
            ?? null;

        if (method_exists($method, 'getTranslation')) {
            try {
                $translation = $locale ? $method->getTranslation($locale) : $method->getTranslation();
                $name = $translation?->getName();
                if (\is_string($name) && $name !== '') {
                    return $name;
                }
            } catch (\Throwable) {
            }
        }

        if (method_exists($method, 'getName') && \is_string($method->getName()) && $method->getName() !== '') {
            return (string) $method->getName();
        }

        return $method->getCode() ?? 'payment';
    }

    private function formatPercent(float $value): string
    {
        $formatted = number_format($value, 2, ',', '.');
        return rtrim(rtrim($formatted, '0'), ',');
    }
}
