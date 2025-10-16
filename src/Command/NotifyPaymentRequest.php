<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\Command;

use Sylius\Bundle\PaymentBundle\Command\PaymentRequestHashAwareInterface;
use Sylius\Bundle\PaymentBundle\Command\PaymentRequestHashAwareTrait;

final class NotifyPaymentRequest implements PaymentRequestHashAwareInterface
{
    use PaymentRequestHashAwareTrait;

    /**
     * @param array<string, mixed> $notificationData
     */
    public function __construct(
        protected ?string $hash,
        private readonly array $notificationData = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getNotificationData(): array
    {
        return $this->notificationData;
    }
}
