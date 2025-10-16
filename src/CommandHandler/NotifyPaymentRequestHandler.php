<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use LakeDynamics\SyliusMoneticoPlugin\Command\NotifyPaymentRequest;
use LakeDynamics\SyliusMoneticoPlugin\Service\MoneticoService;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
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

        // Get the associated payment
        $payment = $paymentRequest->getPayment();
        if (!$payment instanceof PaymentInterface) {
            throw new \RuntimeException('PaymentRequest has no valid payment');
        }

        // Determine if payment is valid
        $isValid = $this->moneticoService->isValidStatus($status);

        // Update PaymentRequest state
        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            $isValid ? PaymentRequestTransitions::TRANSITION_COMPLETE : PaymentRequestTransitions::TRANSITION_FAIL,
        );

        // CRITICAL: Also update the Payment entity state
        // This is what actually marks the payment/order as paid in Sylius
        $this->stateMachine->apply(
            $payment,
            PaymentTransitions::GRAPH,
            $isValid ? PaymentTransitions::TRANSITION_COMPLETE : PaymentTransitions::TRANSITION_FAIL,
        );
    }
}
