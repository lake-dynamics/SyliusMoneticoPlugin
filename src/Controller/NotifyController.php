<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\Controller;

use LakeDynamics\SyliusMoneticoPlugin\Command\NotifyPaymentRequest;
use LakeDynamics\SyliusMoneticoPlugin\Service\MoneticoService;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

final class NotifyController extends AbstractController
{
    /**
     * @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository
     */
    public function __construct(
        private readonly PaymentRequestRepositoryInterface $paymentRequestRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly MoneticoService $moneticoService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // Extract hash from Monetico's texte-libre field
        $texteLibre = $request->isMethod('POST')
            ? $request->request->get('texte-libre')
            : $request->query->get('texte-libre');

        if (null === $texteLibre) {
            throw new \RuntimeException('Missing texte-libre parameter');
        }

        $decodedData = json_decode(base64_decode((string) $texteLibre), true);
        if (!is_array($decodedData) || !isset($decodedData['hash'])) {
            throw new \RuntimeException('Invalid texte-libre format or missing hash');
        }

        $hash = $decodedData['hash'];

        /** @var PaymentRequestInterface|null $paymentRequest */
        $paymentRequest = $this->paymentRequestRepository->findOneBy(['hash' => $hash]);

        if (null === $paymentRequest) {
            throw new \RuntimeException('Payment request not found');
        }

        // Get payment and gateway configuration for MAC validation
        $payment = $paymentRequest->getPayment();
        if (!$payment instanceof PaymentInterface) {
            throw new \RuntimeException('PaymentRequest has no valid payment');
        }

        $paymentMethod = $payment->getMethod();
        if (null === $paymentMethod) {
            throw new \RuntimeException('Payment has no method');
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig()?->getConfig();
        if (!is_array($gatewayConfig) || !isset($gatewayConfig['prod_key'])) {
            throw new \RuntimeException('Payment method has no valid gateway config');
        }

        // Get notification data from request
        $notificationData = $request->isMethod('POST') ? $request->request->all() : $request->query->all();

        /** @var string $prodKey */
        $prodKey = $gatewayConfig['prod_key'];

        // Validate MAC signature
        if (!$this->moneticoService->validateNotification($notificationData, $prodKey)) {
            throw new \RuntimeException('Invalid MAC signature');
        }

        // Dispatch the notification command with the validated data
        $this->messageBus->dispatch(new NotifyPaymentRequest($hash, $notificationData));

        // Return Monetico expected response
        $response = (new Response())->setContent("version=2\ncdr=0\n");
        $response->headers->add(['Content-Type' => 'text/plain']);

        return $response;
    }
}
