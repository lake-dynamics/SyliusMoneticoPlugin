<?php

namespace App\Service\PaymentGateway;

use App\Entity\Transaction;
use App\Event\OrderPaidEvent;
use App\Repository\TransactionRepository;
use App\Service\ClientContextService;
use App\Utils\StringUtil;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MoneticoService implements PaymentServiceInterface
{
    final public const HMAC_SHA256 = 'hmac_sha256';
    final public const PAYMENT_URL = 'https://p.monetico-services.com/paiement.cgi';
    final public const PAYMENT_URL_SANDBOX = 'https://p.monetico-services.com/test/paiement.cgi';

    private bool $debug = false;

    private $key;

    private $fields;

    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly ClientContextService $clientContextService,
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    private function getRedirectionUrl(string $clientRef, bool $paid, array $orderIds): string
    {
        return sprintf('https://manager.kapnor.io/api/%s/payment/redirection?%s',
            $clientRef,
            http_build_query([
                'orderIds' => implode(';', $orderIds),
                'paid' => $paid ? '1' : '0',
                'paymentMethod' => PaymentGatewayManager::MONETICO,
            ])
        );
    }

    public function init(Transaction $transaction, array $options = []): void
    {
        $client = $this->clientContextService->get();

        // Get first order for
        $order = $transaction->getOrders()[0];
        $paymentGateway = $this->clientContextService->get()->findPaymentGateway(PaymentGatewayManager::MONETICO);
        $billingAddress = $order->billingAddress();
        $this->key = $paymentGateway->getProdKey();
        $this->debug = !$paymentGateway->getUseProduction();

        // Order Ids
        $orderIds = [];
        foreach ($transaction->getOrders() as $order) {
            $orderIds[] = $order->getId();
        }

        $this->fields = array_merge([
            'TPE' => $paymentGateway->getSiteId(),
            'societe' => $paymentGateway->getCompanyId(),
            'montant' => ($transaction->getAmount() / 100).'EUR',
            'reference' => $transaction->getExternalId(),
            'lgue' => 'FR',
            'version' => '3.0',
            'date' => gmdate('d/m/Y:H:i:s'),
            'texte-libre' => base64_encode(json_encode(['order_ids' => $orderIds])),
            'contexte_commande' => base64_encode(json_encode([
                'billing' => [
                    'addressLine1' => $billingAddress->line1(),
                    'city' => $billingAddress->city(),
                    'postalCode' => $billingAddress->zip(),
                    'country' => $billingAddress->country(),
                ],
                'shipping' => null,
                'shoppingCart' => null,
                'client' => [
                    'firstName' => $order->getClientFirstname(),
                    'lastName' => $order->getClientLastname(),
                    'email' => $order->getClientEmail(),
                ],
            ])),
            'mail' => $order->getClientEmail(),
            'url_retour_ok' => $this->getRedirectionUrl($client->getRef(), true, $orderIds),
            'url_retour_err' => $this->getRedirectionUrl($client->getRef(), false, $orderIds),
        ], $options);
    }

    public function getOptions(): array
    {
        $fields = $this->fields;
        $fields['MAC'] = $this->sealFields($this->fields, $this->key);

        return $fields;
    }

    public function handleRequest(EntityManagerInterface $em): void
    {
        $client = $this->clientContextService->get();
        $paymentGateway = $client->findPaymentGateway(PaymentGatewayManager::MONETICO);
        $request = $this->requestStack->getCurrentRequest();

        // Set data
        $this->key = $paymentGateway->getProdKey();

        // Find transaction
        $reference = $request->get('reference');
        $details = json_decode(base64_decode((string) $request->get('texte-libre')), true);

        if (empty($details['order_ids']) || !$reference) {
            throw new \Exception('Monetico : reference or order ids not found');
        }

        $orderIds = $details['order_ids'];
        $transaction = $this->transactions->findByExternalIdAndOrderIds($reference, $orderIds);

        $fields = $request->isMethod('POST') ? $_POST : $_GET;
        $seal = $fields['MAC'] ?? null;
        unset($fields['MAC']);

        // Check signature
        if (null === $seal || !$this->validateSeal($fields, $this->key, $seal)) {
            throw new \Exception('MAC is not valid');
        }

        $status = $fields['code-retour'];
        $cardDetails = substr((string) $request->get('cbmasquee', 'NaaN'), -4);

        // Signature is valid, update transaction
        $transaction->setError($request->get('motifrefus', null));

        // Payment is valid
        if (self::isValidStatus($status)) {
            $transaction->pay();
            $em->flush();
            // Dispatch order paid
            foreach ($transaction->getOrders() as $order) {
                $order->setCardDetails($cardDetails);
                $this->eventDispatcher->dispatch(new OrderPaidEvent($order));
            }
        } else {
            $transaction->refuse();
        }
    }

    public function getPaymentUrl(): string
    {
        return $this->debug ? self::PAYMENT_URL_SANDBOX : self::PAYMENT_URL;
    }

    public function createTransaction(array $orders): Transaction
    {
        $client = $this->clientContextService->get();

        // Generate and check that trans id is unique for today
        do {
            $externalId = strtoupper(StringUtil::random(StringUtil::TYPE_ALPHANUMERIC, 12));
        } while ($this->transactions->findByExternalIdAndClient($externalId, $client));

        return Transaction::create($orders, $externalId, PaymentGatewayManager::MONETICO);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, ['payetest', 'paiement']);
    }

    public function sealFields(array $fields, string $key)
    {
        $stringToSeal = $this->getStringToSeal($fields);

        return $this->sealString($stringToSeal, $this->getUsableKey($key));
    }

    public function validateSeal(array $fields, string $key, string $expectedSeal): bool
    {
        if (null !== $fields) {
            return strtoupper((string) $this->sealFields($fields, $key)) === strtoupper($expectedSeal);
        }

        return false;
    }

    public function getStringToSeal(array $formFields)
    {
        ksort($formFields);
        array_walk($formFields, function (&$item, $key) {
            $item = "$key=$item";
        });

        return implode('*', $formFields);
    }

    private function sealString(string $stringToSeal, string $key): string
    {
        return hash_hmac('sha1', $stringToSeal, hex2bin($key));
    }

    private function getUsableKey(string $key)
    {
        $hexStrKey = substr($key, 0, 38);
        $hexFinal = ''.substr($key, 38, 2).'00';

        $cca0 = ord($hexFinal);

        if ($cca0 > 70 && $cca0 < 97) {
            $hexStrKey .= chr($cca0 - 23).substr($hexFinal, 1, 1);
        } else {
            if ('M' == substr($hexFinal, 1, 1)) {
                $hexStrKey .= substr($hexFinal, 0, 1).'0';
            } else {
                $hexStrKey .= substr($hexFinal, 0, 2);
            }
        }

        return $hexStrKey;
    }
}
