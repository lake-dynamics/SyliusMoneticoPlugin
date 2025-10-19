<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use LakeDynamics\SyliusMoneticoPlugin\Command\NotifyPaymentRequest;
use LakeDynamics\SyliusMoneticoPlugin\Service\MoneticoService;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
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

        // Get the associated payment
        $payment = $paymentRequest->getPayment();
        if (!$payment instanceof PaymentInterface) {
            throw new \RuntimeException('PaymentRequest has no valid payment');
        }

        // Get notification data from the command (already validated by the controller)
        $data = $notifyPaymentRequest->getNotificationData();
        $status = mb_strtolower($data['code-retour'] ?? '');
        $isValid = $this->moneticoService->isValidStatus($status);
        $isFailed = $this->moneticoService->isFailedStatus($status);
        $isRefunded = $this->moneticoService->isRefundedStatus($status);

        // Store notification data in response
        $paymentRequest->setResponseData([
            'status' => $status,
            'card_details' => substr((string) ($data['cbmasquee'] ?? 'NaaN'), -4),
            'motif_refus' => $data['motifrefus'] ?? null,
            'notification_data' => $data,
        ]);

        // Get order from payment
        $order = $payment->getOrder();
        if (!$order instanceof OrderInterface) {
            throw new \RuntimeException('Payment has no order');
        }

        // Save payment details
        $payment->setDetails($data);

        if ($isValid) {
            // Order transitions to pay
            if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_PAY)) {
                $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_PAY);
            }
            // Payment transitions to complete
            if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)) {
                $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);
            }

            // Make payment request complete
            if ($this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE)) {
                $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
            }
        } elseif ($isFailed) {
            // Payment transitions to fail
            if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL)) {
                $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);
            }
            // Make payment request fail
            if ($this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL)) {
                $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);
            }
        }

        // Flush all changes atomically (response data + state transitions)
        $this->entityManager->flush();
    }
}
