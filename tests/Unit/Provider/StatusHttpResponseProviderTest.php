<?php

declare(strict_types=1);

namespace Tests\LakeDynamics\SyliusMoneticoPlugin\Unit\Provider;

use LakeDynamics\SyliusMoneticoPlugin\Provider\StatusHttpResponseProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class StatusHttpResponseProviderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;

    private StatusHttpResponseProvider $provider;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->provider = new StatusHttpResponseProvider(
            $this->urlGenerator,
            'sylius_shop_order_show',
            'sylius_shop_order_thank_you',
        );
    }

    public function testItSupportsStatusAction(): void
    {
        $requestConfiguration = $this->createMock(RequestConfiguration::class);
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_STATUS);

        self::assertTrue($this->provider->supports($requestConfiguration, $paymentRequest));
    }

    public function testItDoesNotSupportNonStatusAction(): void
    {
        $requestConfiguration = $this->createMock(RequestConfiguration::class);
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);

        self::assertFalse($this->provider->supports($requestConfiguration, $paymentRequest));
    }

    public function testItRedirectsSuccessfulPaymentToSuccessRoute(): void
    {
        $requestConfiguration = $this->createMock(RequestConfiguration::class);
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_COMPLETED);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);

        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/thank-you');

        $response = $this->provider->getResponse($requestConfiguration, $paymentRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        /** @var RedirectResponse $response */
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://example.com/thank-you', $response->getTargetUrl());
    }

    public function testItRedirectsFailedPaymentToFailedRouteWithTokenValueForOrderShowRoute(): void
    {
        $requestConfiguration = $this->createMock(RequestConfiguration::class);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('order-token');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_FAILED);
        $payment->method('getOrder')->willReturn($order);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);

        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with(
                'sylius_shop_order_show',
                ['tokenValue' => 'order-token'],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn('https://example.com/order/show');

        $response = $this->provider->getResponse($requestConfiguration, $paymentRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        /** @var RedirectResponse $response */
        self::assertSame('https://example.com/order/show', $response->getTargetUrl());
    }

    public function testItRedirectsFailedPaymentToCustomRouteWithoutTokenValue(): void
    {
        $provider = new StatusHttpResponseProvider(
            $this->urlGenerator,
            'app_payment_failed',
            'sylius_shop_order_thank_you',
        );

        $requestConfiguration = $this->createMock(RequestConfiguration::class);
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_CANCELLED);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);

        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('app_payment_failed', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/payment/failed');

        $response = $provider->getResponse($requestConfiguration, $paymentRequest);

        self::assertInstanceOf(RedirectResponse::class, $response);
        /** @var RedirectResponse $response */
        self::assertSame('https://example.com/payment/failed', $response->getTargetUrl());
    }

    public function testItThrowsWhenPaymentRequestHasNoValidPayment(): void
    {
        $requestConfiguration = $this->createMock(RequestConfiguration::class);
        $payment = $this->createMock(BasePaymentInterface::class);
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PaymentRequest has no valid payment');

        $this->provider->getResponse($requestConfiguration, $paymentRequest);
    }

    public function testItThrowsWhenPaymentHasNoOrderForOrderShowRoute(): void
    {
        $requestConfiguration = $this->createMock(RequestConfiguration::class);
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_FAILED);
        $payment->method('getOrder')->willReturn(null);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment has no order');

        $this->provider->getResponse($requestConfiguration, $paymentRequest);
    }
}
