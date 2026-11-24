<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Payments\PaymentGatewayCallbackReference;
use App\Models\Payment;
use App\Services\Payments\BkashCheckoutService;
use App\Services\Payments\MfsMerchantConfigurationService;
use RuntimeException;
use Throwable;

class BkashGateway implements PaymentGateway, PaymentGatewayCallbackReference
{
    private const ALLOWED_CHECKOUT_HOSTS = [
        'tokenized.sandbox.bka.sh',
        'checkout.sandbox.bka.sh',
    ];

    public function __construct(
        private readonly BkashCheckoutService $client,
        private readonly MfsMerchantConfigurationService $merchantConfiguration
    )
    {
    }

    public function key(): string
    {
        return 'bkash';
    }

    public function label(): string
    {
        return 'bKash Tokenized Checkout';
    }

    public function supportedCurrencies(): array
    {
        return ['BDT'];
    }

    public function isConfigured(): bool
    {
        return $this->merchantConfiguration->isBkashConfigured();
    }

    public function createCheckout(Payment $payment, string $callbackUrl, string $payerReference): array
    {
        if (! $this->isConfigured() || ! in_array(strtoupper((string) $payment->currency), $this->supportedCurrencies(), true)) {
            throw new RuntimeException('bKash is not configured for this payment currency.');
        }

        $token = $this->client->grantToken();
        $created = $this->client->createPayment(
            $token,
            number_format((float) $payment->amount, 2, '.', ''),
            (string) $payment->transaction_reference,
            $callbackUrl,
            $payerReference
        );

        $paymentId = $created['paymentID'] ?? null;
        $checkoutUrl = $created['bkashURL'] ?? null;
        $host = is_string($checkoutUrl) ? parse_url($checkoutUrl, PHP_URL_HOST) : null;

        if (($created['statusCode'] ?? null) !== '0000'
            || ! is_string($paymentId) || $paymentId === ''
            || ! is_string($checkoutUrl)
            || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https'
            || ! in_array($host, self::ALLOWED_CHECKOUT_HOSTS, true)) {
            throw new RuntimeException('bKash returned an invalid checkout response.');
        }

        return ['payment_id' => $paymentId, 'checkout_url' => $checkoutUrl];
    }

    public function callbackPaymentId(array $callback): ?string
    {
        $paymentId = $callback['paymentID'] ?? null;

        return is_string($paymentId) && $paymentId !== '' ? $paymentId : null;
    }

    /** @param array{verified:array<string,mixed>,executed:array<string,mixed>} $verification
     *  @return array{status:string,payment_id:mixed,transaction_id:mixed,amount:mixed,currency:mixed,merchant_reference:mixed}
     */
    public function normalizeCallbackVerification(array $verification): array
    {
        $verified = $verification['verified'] ?? [];
        $executed = $verification['executed'] ?? [];

        return [
            'status' => strtolower((string) ($verified['transactionStatus'] ?? '')),
            'payment_id' => $verified['paymentID'] ?? null,
            'transaction_id' => $verified['trxID'] ?? null,
            'amount' => $verified['amount'] ?? null,
            'currency' => $verified['currency'] ?? null,
            'merchant_reference' => $verified['merchantInvoiceNumber'] ?? $executed['merchantInvoiceNumber'] ?? null,
        ];
    }

    public function verifyCallback(Payment $payment, array $callback): array
    {
        $paymentId = $callback['paymentID'] ?? null;
        if (! is_string($paymentId) || $paymentId === '' || $paymentId !== $payment->gateway_payment_id) {
            throw new RuntimeException('The bKash callback payment reference is invalid.');
        }

        $token = $this->client->grantToken();
        $executed = [];
        if (strtolower((string) ($callback['status'] ?? '')) === 'success') {
            try {
                $executed = $this->client->executePayment($token, $paymentId);
            } catch (Throwable) {
                // Repeated callbacks are safe; the authoritative status query remains mandatory.
            }
        }

        return [
            'verified' => $this->client->queryPayment($token, $paymentId),
            'executed' => $executed,
        ];
    }
}
