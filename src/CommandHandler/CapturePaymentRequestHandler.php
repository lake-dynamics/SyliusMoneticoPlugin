<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\CommandHandler;

use LakeDynamics\SyliusMoneticoPlugin\Command\CapturePaymentRequest;
use LakeDynamics\SyliusMoneticoPlugin\Service\MoneticoService;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class CapturePaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private MoneticoService $moneticoService,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(CapturePaymentRequest $capturePaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($capturePaymentRequest);
        $payment = $paymentRequest->getPayment();

        if (!$payment instanceof PaymentInterface) {
            throw new \RuntimeException('PaymentRequest has no valid payment');
        }

        $paymentMethod = $payment->getMethod();
        if (null === $paymentMethod) {
            throw new \RuntimeException('Payment has no method');
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig()?->getConfig();
        if (null === $gatewayConfig) {
            throw new \RuntimeException('Payment method has no gateway config');
        }

        // Generate success and error URLs
        $order = $payment->getOrder();
        if (null === $order) {
            throw new \RuntimeException('Payment has no order');
        }

        $unifiedReturnUrl = $this->urlGenerator->generate(
            'sylius_shop_order_after_pay',
            ['hash' => $paymentRequest->getHash()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        // Prepare payment fields
        $hash = $paymentRequest->getHash();
        if (null === $hash) {
            throw new \RuntimeException('PaymentRequest has no hash');
        }

        $paymentFields = $this->moneticoService->preparePaymentFields(
            $payment,
            $gatewayConfig,
            $unifiedReturnUrl,
            $unifiedReturnUrl,
            $hash->toRfc4122(),
        );

        // Store payment data in PaymentRequest response data
        $paymentRequest->setPayload([
            'payment_url' => $this->moneticoService->getPaymentUrl($gatewayConfig),
            'payment_fields' => $paymentFields,
        ]);
    }
}
