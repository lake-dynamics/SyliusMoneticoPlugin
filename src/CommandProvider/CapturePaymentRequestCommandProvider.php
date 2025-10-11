<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\CommandProvider;

use LakeDynamics\SyliusMoneticoPlugin\Command\CapturePaymentRequest;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final readonly class CapturePaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_CAPTURE;
    }

    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        $hash = $paymentRequest->getHash();

        return new CapturePaymentRequest($hash?->toRfc4122());
    }
}
