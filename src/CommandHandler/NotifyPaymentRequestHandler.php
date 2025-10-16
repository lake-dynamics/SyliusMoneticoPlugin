<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use LakeDynamics\SyliusMoneticoPlugin\Command\NotifyPaymentRequest;
use LakeDynamics\SyliusMoneticoPlugin\Service\MoneticoService;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class NotifyPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private MoneticoService $moneticoService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(NotifyPaymentRequest $notifyPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($notifyPaymentRequest);

        // Get notification data from the command (already validated by the controller)
        $data = $notifyPaymentRequest->getNotificationData();

        $status = $data['code-retour'] ?? '';

        // Store notification data in response
        $paymentRequest->setResponseData([
            'status' => $status,
            'card_details' => substr((string) ($data['cbmasquee'] ?? 'NaaN'), -4),
            'motif_refus' => $data['motifrefus'] ?? null,
        ]);

        $this->entityManager->flush();

        // Update payment state based on status
        if ($this->moneticoService->isValidStatus($status)) {
            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_COMPLETE,
            );
        } else {
            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_FAIL,
            );
        }
    }
}
