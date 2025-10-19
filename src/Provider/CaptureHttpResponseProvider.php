<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\Provider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class CaptureHttpResponseProvider implements HttpResponseProviderInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function supports(RequestConfiguration $requestConfiguration, PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_CAPTURE;
    }

    public function getResponse(RequestConfiguration $requestConfiguration, PaymentRequestInterface $paymentRequest): Response
    {
        $payload = $paymentRequest->getPayload();

        // Decode JSON payload if it's a string
        if (is_string($payload)) {
            $data = json_decode($payload, true);
            if (null === $data) {
                throw new \RuntimeException('Invalid JSON payload');
            }
        } else {
            $data = $payload;
        }

        if (!isset($data['payment_url']) || !isset($data['payment_fields'])) {
            throw new \RuntimeException('Payment data not prepared');
        }

        return new Response(
            $this->twig->render(
                '@LakeDynamicsSyliusMoneticoPlugin/payment/monetico_redirect.html.twig',
                [
                    'payment_url' => $data['payment_url'],
                    'payment_fields' => $data['payment_fields'],
                ],
            ),
        );
    }
}
