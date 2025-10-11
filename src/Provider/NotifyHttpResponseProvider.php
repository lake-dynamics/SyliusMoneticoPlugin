<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\Provider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class NotifyHttpResponseProvider implements HttpResponseProviderInterface
{
    public function supports(RequestConfiguration $requestConfiguration, PaymentRequestInterface $paymentRequest): bool
    {
        return $paymentRequest->getAction() === 'notify';
    }

    public function getResponse(RequestConfiguration $requestConfiguration, PaymentRequestInterface $paymentRequest): Response
    {
        // Monetico expects "version=2" response
        return new Response('version=2', Response::HTTP_OK);
    }
}
