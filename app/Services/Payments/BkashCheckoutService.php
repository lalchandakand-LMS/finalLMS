<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BkashCheckoutService
{
    public function __construct(private readonly MfsMerchantConfigurationService $merchantConfiguration)
    {
    }

    private function credential(string $key): string
    {
        return (string) ($this->merchantConfiguration->credential('bkash', $key) ?? '');
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('services.bkash.base_url'), '/');
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT);
        $hasUserInfo = parse_url($baseUrl, PHP_URL_USER) !== null || parse_url($baseUrl, PHP_URL_PASS) !== null;
        $hasQueryOrFragment = parse_url($baseUrl, PHP_URL_QUERY) !== null || parse_url($baseUrl, PHP_URL_FRAGMENT) !== null;

        if ($scheme !== 'https' || $host !== 'tokenized.sandbox.bka.sh'
            || ($port !== null && $port !== 443) || $hasUserInfo || $hasQueryOrFragment
            || ! (bool) config('services.bkash.verify_ssl', true)) {
            throw new RuntimeException('Only the verified bKash sandbox endpoint is allowed.');
        }

        return $baseUrl;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->withOptions(['verify' => (bool) config('services.bkash.verify_ssl', true)]);
    }

    public function grantToken(): string
    {
        $response = $this->request()
            ->withHeaders([
                'username' => (string) $this->credential('username'),
                'password' => (string) $this->credential('password'),
                'X-APP-Key' => (string) $this->credential('app_key'),
            ])
            ->post($this->baseUrl().'/tokenized/checkout/token/grant', [
                'app_key' => (string) $this->credential('app_key'),
                'app_secret' => (string) $this->credential('app_secret'),
            ])
            ->throw()
            ->json();

        $token = $response['id_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('bKash did not return an access token.');
        }

        return $token;
    }

    public function createPayment(string $token, string $amount, string $invoice, string $callbackUrl, string $payerReference): array
    {
        return $this->request()
            ->withToken($token)
            ->withHeader('X-APP-Key', (string) $this->credential('app_key'))
            ->post($this->baseUrl().'/tokenized/checkout/create', [
                'mode' => '0011',
                'payerReference' => $payerReference,
                'callbackURL' => $callbackUrl,
                'amount' => $amount,
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $invoice,
            ])
            ->throw()
            ->json();
    }

    public function executePayment(string $token, string $paymentId): array
    {
        return $this->request()
            ->withToken($token)
            ->withHeader('X-APP-Key', (string) $this->credential('app_key'))
            ->post($this->baseUrl().'/tokenized/checkout/execute', [
                'paymentID' => $paymentId,
            ])
            ->throw()
            ->json();
    }

    public function refundPayment(string $token, string $paymentId, string $amount, string $transactionId, string $reason): array
    {
        return $this->request()->withToken($token)
            ->withHeader('X-APP-Key', (string) $this->credential('app_key'))
            ->post($this->baseUrl().'/tokenized/checkout/payment/refund', [
                'paymentID' => $paymentId, 'amount' => $amount, 'trxID' => $transactionId,
                'sku' => 'LMS-REFUND', 'reason' => mb_substr($reason, 0, 200),
            ])->throw()->json();
    }

    public function queryRefund(string $token, string $paymentId, string $originalTransactionId): array
    {
        return $this->request()->withToken($token)
            ->withHeader('X-APP-Key', (string) $this->credential('app_key'))
            ->post($this->baseUrl().'/tokenized/checkout/payment/refund/status', [
                'paymentID' => $paymentId, 'trxID' => $originalTransactionId,
            ])->throw()->json();
    }

    public function queryPayment(string $token, string $paymentId): array
    {
        return $this->request()
            ->withToken($token)
            ->withHeader('X-APP-Key', (string) $this->credential('app_key'))
            ->post($this->baseUrl().'/tokenized/checkout/payment/status', [
                'paymentID' => $paymentId,
            ])
            ->throw()
            ->json();
    }
}
