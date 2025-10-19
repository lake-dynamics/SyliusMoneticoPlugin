<?php

declare(strict_types=1);

namespace LakeDynamics\SyliusMoneticoPlugin\Service;

use Sylius\Component\Core\Model\PaymentInterface;

final class MoneticoService
{
    public const PAYMENT_URL = 'https://p.monetico-services.com/paiement.cgi';

    public const PAYMENT_URL_SANDBOX = 'https://p.monetico-services.com/test/paiement.cgi';

    public function getPaymentUrl(array $gatewayConfig): string
    {
        $useProduction = $gatewayConfig['use_production'] ?? false;

        return $useProduction ? self::PAYMENT_URL : self::PAYMENT_URL_SANDBOX;
    }

    /**
     * @return array<string, mixed>
     */
    public function preparePaymentFields(
        PaymentInterface $payment,
        array $gatewayConfig,
        string $successUrl,
        string $errorUrl,
        string $paymentRequestHash,
    ): array {
        $order = $payment->getOrder();
        if (null === $order) {
            throw new \RuntimeException('Payment has no order');
        }

        $billingAddress = $order->getBillingAddress();
        if (null === $billingAddress) {
            throw new \RuntimeException('Order has no billing address');
        }

        $customer = $order->getCustomer();
        if (null === $customer) {
            throw new \RuntimeException('Order has no customer');
        }

        // Generate unique payment reference
        $reference = $this->generateReference($payment);

        $fields = [
            'TPE' => $gatewayConfig['tpe'],
            'societe' => $gatewayConfig['company_id'],
            'montant' => $this->formatAmount($payment->getAmount()) . 'EUR',
            'reference' => $reference,
            'lgue' => 'FR',
            'version' => '3.0',
            'date' => gmdate('d/m/Y:H:i:s'),
            'texte-libre' => base64_encode((string) json_encode([
                'payment_id' => $payment->getId(),
                'order_id' => $order->getId(),
                'hash' => $paymentRequestHash,
            ])),
            'contexte_commande' => base64_encode((string) json_encode([
                'billing' => [
                    'addressLine1' => $billingAddress->getStreet(),
                    'city' => $billingAddress->getCity(),
                    'postalCode' => $billingAddress->getPostcode(),
                    'country' => $billingAddress->getCountryCode(),
                ],
                'client' => [
                    'firstName' => $customer->getFirstName(),
                    'lastName' => $customer->getLastName(),
                    'email' => $customer->getEmail(),
                ],
            ])),
            'mail' => $customer->getEmail(),
            'url_retour_ok' => $successUrl,
            'url_retour_err' => $errorUrl,
        ];

        // Add MAC signature
        $fields['MAC'] = $this->sealFields($fields, $gatewayConfig['prod_key']);

        return $fields;
    }

    public function validateNotification(array $data, string $key): bool
    {
        $seal = $data['MAC'] ?? null;
        if (null === $seal) {
            return false;
        }

        unset($data['MAC']);

        return $this->validateSeal($data, $key, $seal);
    }

    public function isValidStatus(string $status): bool
    {
        return in_array($status, ['payetest', 'paiement'], true);
    }

    public function isFailedStatus(string $status): bool
    {
        return in_array($status, ['annulation'], true);
    }

    public function isRefundedStatus(string $status): bool
    {
        return in_array($status, ['remboursement'], true);
    }
    private function generateReference(PaymentInterface $payment): string
    {
        return strtoupper(sprintf(
            '%s-%d',
            substr(md5((string) time()), 0, 8),
            $payment->getId(),
        ));
    }

    private function formatAmount(?int $amount): string
    {
        if (null === $amount) {
            return '0.00';
        }

        return number_format($amount / 100, 2, '.', '');
    }

    private function sealFields(array $fields, string $key): string
    {
        $stringToSeal = $this->getStringToSeal($fields);

        return $this->sealString($stringToSeal, $this->getUsableKey($key));
    }

    private function validateSeal(array $fields, string $key, string $expectedSeal): bool
    {
        return strtoupper($this->sealFields($fields, $key)) === strtoupper($expectedSeal);
    }

    private function getStringToSeal(array $formFields): string
    {
        ksort($formFields);
        array_walk($formFields, function (&$item, $key): void {
            $item = "$key=$item";
        });

        return implode('*', $formFields);
    }

    private function sealString(string $stringToSeal, string $key): string
    {
        $binaryKey = hex2bin($key);
        if (false === $binaryKey) {
            throw new \RuntimeException('Invalid hex key');
        }

        return hash_hmac('sha1', $stringToSeal, $binaryKey);
    }

    private function getUsableKey(string $key): string
    {
        $hexStrKey = substr($key, 0, 38);
        $hexFinal = '' . substr($key, 38, 2) . '00';

        $cca0 = ord($hexFinal);

        if ($cca0 > 70 && $cca0 < 97) {
            $hexStrKey .= chr($cca0 - 23) . substr($hexFinal, 1, 1);
        } else {
            if ('M' === substr($hexFinal, 1, 1)) {
                $hexStrKey .= substr($hexFinal, 0, 1) . '0';
            } else {
                $hexStrKey .= substr($hexFinal, 0, 2);
            }
        }

        return $hexStrKey;
    }
}
