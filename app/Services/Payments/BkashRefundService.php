<?php

namespace App\Services\Payments;

use RuntimeException;

class BkashRefundService
{
    public function refund(string $paymentId, string $transactionId, string $amount, string $reason): array
    {
        if (! config('services.bkash.enabled') || ! config('services.bkash.verify_ssl', true)) {
            throw new RuntimeException('bKash refund processing is not enabled with verified TLS.');
        }

        $client = app(BkashCheckoutService::class);
        $token = $client->grantToken();
        $response = $client->refundPayment($token, $paymentId, $amount, $transactionId, $reason);

        $refundId = $response['trxID'] ?? null;
        if (($response['statusCode'] ?? null) !== '0000'
            || ! is_string($refundId) || $refundId === ''
            || strtoupper((string) ($response['currency'] ?? 'BDT')) !== 'BDT'
            || round((float) ($response['amount'] ?? -1), 2) !== round((float) $amount, 2)
            || (isset($response['paymentID']) && $response['paymentID'] !== $paymentId)) {
            throw new RuntimeException('bKash did not confirm the requested refund. Manual gateway reconciliation is required.');
        }

        $verified = $client->queryRefund($token, $paymentId, $refundId);
        if (($verified['statusCode'] ?? null) !== '0000'
            || strtolower((string) ($verified['transactionStatus'] ?? '')) !== 'completed'
            || ($verified['trxID'] ?? null) !== $refundId
            || round((float) ($verified['amount'] ?? -1), 2) !== round((float) $amount, 2)
            || (isset($verified['paymentID']) && $verified['paymentID'] !== $paymentId)) {
            throw new RuntimeException('bKash refund status could not be independently verified.');
        }

        return [
            'refund_id' => $refundId,
            'status' => (string) ($verified['transactionStatus'] ?? 'Completed'),
            'amount' => number_format((float) $verified['amount'], 2, '.', ''),
        ];
    }
}
