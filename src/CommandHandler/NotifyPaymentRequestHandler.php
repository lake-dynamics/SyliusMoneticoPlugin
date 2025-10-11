<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use LakeDynamics\SyliusMoneticoPlugin\Command\NotifyPaymentRequest;
use LakeDynamics\SyliusMoneticoPlugin\Service\MoneticoService;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class NotifyPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private MoneticoService $moneticoService,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(NotifyPaymentRequest $notifyPaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($notifyPaymentRequest);
        $payment = $paymentRequest->getPayment();

        if (!$payment instanceof \Sylius\Component\Core\Model\PaymentInterface) {
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

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new \RuntimeException('No current request');
        }

        // Get POST data
        $data = $request->isMethod('POST') ? $request->request->all() : $request->query->all();

        /** @var string $prodKey */
        $prodKey = $gatewayConfig['prod_key'];

        // Validate MAC signature
        if (!$this->moneticoService->validateNotification($data, $prodKey)) {
            throw new \RuntimeException('Invalid MAC signature');
        }

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
