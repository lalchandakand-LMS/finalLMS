<?php

namespace App\\Services\\Payments;

use Illuminate\\Http\\Client\\PendingRequest;
use Illuminate\\Support\\Facades\\Http;
use RuntimeException;

class BkashCheckoutService
{
    private function baseUrl(): string
    {
        return rtrim((string) config('services.bkash.base_url'), '/');
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
                'username' => (string) config('services.bkash.username'),
                'password' => (string) config('services.bkash.password'),
                'X-APP-Key' => (string) config('services.bkash.app_key'),
            ])
            ->post($this->baseUrl().'/tokenized/checkout/token/grant', [
                'app_key' => (string) config('services.bkash.app_key'),
                'app_secret' => (string) config('services.bkash.app_secret'),
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
            ->withHeader('X-APP-Key', (string) config('services.bkash.app_key'))
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
            ->withHeader('X-APP-Key', (string) config('services.bkash.app_key'))
            ->post($this->baseUrl().'/tokenized/checkout/execute', [
                'paymentID' => $paymentId,
            ])
            ->throw()
            ->json();
    }

    public function queryPayment(string $token, string $paymentId): array
    {
        return $this->request()
            ->withToken($token)
            ->withHeader('X-APP-Key', (string) config('services.bkash.app_key'))
            ->post($this->baseUrl().'/tokenized/checkout/payment/status', [
                'paymentID' => $paymentId,
            ])
            ->throw()
            ->json();
    }
}
