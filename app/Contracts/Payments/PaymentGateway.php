<?php

namespace App\Contracts\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    public function key(): string;
    public function label(): string;
    /** @return list<string> */
    public function supportedCurrencies(): array;
    public function isConfigured(): bool;

    /** @return array{payment_id:string,checkout_url:string} */
    public function createCheckout(Payment $payment, string $callbackUrl, string $payerReference): array;

    /**
     * @param array<string, mixed> $callback Untrusted provider callback data.
     * @return array{verified:array{status:string,payment_id:string,transaction_id:string,amount:int|float|string,currency:string,merchant_reference:string},executed:array<string,mixed>}
     *
     * The verified fields must come from an authoritative server-to-server provider response,
     * not from the browser callback. The Payment Core binds them to the local payment.
     */
    public function verifyCallback(Payment $payment, array $callback): array;
}
