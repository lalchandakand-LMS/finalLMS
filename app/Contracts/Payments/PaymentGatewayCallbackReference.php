<?php

namespace App\Contracts\Payments;

/**
 * Optional capability for gateways that can safely identify and normalize a
 * browser callback after authoritative server-side verification.
 */
interface PaymentGatewayCallbackReference
{
    /** @param array<string, mixed> $callback */
    public function callbackPaymentId(array $callback): ?string;

    /**
     * @param array{verified:array<string,mixed>,executed:array<string,mixed>} $verification
     * @return array{status:string,payment_id:mixed,transaction_id:mixed,amount:mixed,currency:mixed,merchant_reference:mixed}
     */
    public function normalizeCallbackVerification(array $verification): array;
}
