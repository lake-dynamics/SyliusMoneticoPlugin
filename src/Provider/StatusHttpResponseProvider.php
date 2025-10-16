<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\Provider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class StatusHttpResponseProvider implements HttpResponseProviderInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(RequestConfiguration $requestConfiguration, PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_STATUS;
    }

    public function getResponse(RequestConfiguration $requestConfiguration, PaymentRequestInterface $paymentRequest): Response
    {
        $payment = $paymentRequest->getPayment();

        if (!$payment instanceof PaymentInterface) {
            throw new \RuntimeException('PaymentRequest has no valid payment');
        }

        $order = $payment->getOrder();
        if (null === $order) {
            throw new \RuntimeException('Payment has no order');
        }

        // Redirect to the order thank you page
        $url = $this->urlGenerator->generate(
            'sylius_shop_order_thank_you',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new RedirectResponse($url);
    }
}
