<?php

namespace Coolminds\PayByInvoice;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class CoolmindsPayByInvoicePlugin extends Bundle
{
    use SyliusPluginTrait;
}
