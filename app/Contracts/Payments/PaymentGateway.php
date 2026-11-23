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

    /** @param array<string, mixed> $callback
     *  @return array{verified:array<string,mixed>,executed:array<string,mixed>}
     */
    /** Return verified fields normalized to status, payment_id, transaction_id, amount, currency and merchant_reference. */
    public function verifyCallback(Payment $payment, array $callback): array;
}
