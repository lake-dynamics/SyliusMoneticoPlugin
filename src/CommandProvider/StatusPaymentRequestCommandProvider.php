<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\CommandProvider;

use LakeDynamics\SyliusMoneticoPlugin\Command\StatusPaymentRequest;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final readonly class StatusPaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_STATUS;
    }

    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        $hash = $paymentRequest->getHash();

        return new StatusPaymentRequest($hash?->toRfc4122());
    }
}
