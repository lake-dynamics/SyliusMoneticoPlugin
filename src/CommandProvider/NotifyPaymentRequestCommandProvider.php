<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\CommandProvider;

use LakeDynamics\SyliusMoneticoPlugin\Command\NotifyPaymentRequest;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final readonly class NotifyPaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === 'notify';
    }

    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        $hash = $paymentRequest->getHash();

        return new NotifyPaymentRequest($hash?->toRfc4122());
    }
}
