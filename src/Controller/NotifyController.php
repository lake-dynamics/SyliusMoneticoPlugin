<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\Controller;

use LakeDynamics\SyliusMoneticoPlugin\Command\NotifyPaymentRequest;
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
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // Extract hash from Monetico's texte-libre field
        $texteLibre = $request->isMethod('POST')
            ? $request->request->get('texte-libre')
            : $request->query->get('texte-libre');

        if (null === $texteLibre) {
            throw $this->createNotFoundException('Missing texte-libre parameter');
        }

        $decodedData = json_decode(base64_decode((string) $texteLibre), true);
        if (!is_array($decodedData) || !isset($decodedData['hash'])) {
            throw $this->createNotFoundException('Invalid texte-libre format or missing hash');
        }

        $hash = $decodedData['hash'];

        /** @var PaymentRequestInterface|null $paymentRequest */
        $paymentRequest = $this->paymentRequestRepository->findOneBy(['hash' => $hash]);

        if (null === $paymentRequest) {
            throw $this->createNotFoundException('Payment request not found');
        }

        // Dispatch the notification command
        $this->messageBus->dispatch(new NotifyPaymentRequest($hash));

        // Return Monetico expected response
        return new Response('version=2', Response::HTTP_OK);
    }
}
