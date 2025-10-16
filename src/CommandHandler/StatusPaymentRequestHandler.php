<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\CommandHandler;

use LakeDynamics\SyliusMoneticoPlugin\Command\StatusPaymentRequest;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StatusPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
    ) {
    }

    public function __invoke(StatusPaymentRequest $statusPaymentRequest): void
    {
        // Simply provide the payment request to ensure it exists
        // The actual payment state is set by the NotifyPaymentRequestHandler
        // This handler is called when the user returns from Monetico payment page
        $this->paymentRequestProvider->provide($statusPaymentRequest);
    }
}
