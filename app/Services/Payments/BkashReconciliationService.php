<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\InvoicePayment;
use App\Models\PaymentAuditEvent;
use App\Models\PaymentReconciliation;
use Illuminate\Support\Facades\DB;
use Throwable;

class BkashReconciliationService
{
    public function reconcileInvoicePayment(InvoicePayment $payment): PaymentReconciliation
    {
        $checkedAt = now();
        $verified = [];
        $error = null;

        try {
            if ($payment->gateway_provider !== 'bkash' || ! $payment->gateway_payment_id || ! $payment->gateway_transaction_id) {
                throw new \\RuntimeException('Invoice payment has incomplete bKash gateway references.');
            }
            $client = app(BkashCheckoutService::class);
            $verified = $client->queryPayment($client->grantToken(), $payment->gateway_payment_id);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $gatewayStatus = strtolower((string) ($verified['transactionStatus'] ?? ''));
        $gatewayAmount = isset($verified['amount']) ? round((float) $verified['amount'], 2) : null;
        $gatewayId = (string) ($verified['paymentID'] ?? '');
        $gatewayTrx = (string) ($verified['trxID'] ?? '');
        $currency = strtoupper((string) ($verified['currency'] ?? ''));
        $matches = $error === null && $gatewayStatus === 'completed'
            && $gatewayId === $payment->gateway_payment_id
            && $gatewayTrx === $payment->gateway_transaction_id
            && $gatewayAmount !== null && $gatewayAmount === round((float) $payment->amount, 2)
            && $currency === 'BDT';
        $result = $error !== null ? 'error' : ($matches ? 'matched' : 'mismatch');

        return DB::transaction(function () use ($payment, $checkedAt, $gatewayStatus, $gatewayAmount, $gatewayTrx, $currency, $result, $error): PaymentReconciliation {
            $record = PaymentReconciliation::create([
                'invoice_payment_id' => $payment->id,
                'provider' => 'bkash',
                'gateway_payment_id' => $payment->gateway_payment_id,
                'gateway_transaction_id' => $gatewayTrx ?: $payment->gateway_transaction_id,
                'result' => $result,
                'local_status' => 'recorded',
                'gateway_status' => $gatewayStatus ?: null,
                'local_amount' => $payment->amount,
                'gateway_amount' => $gatewayAmount,
                'currency' => $currency ?: 'BDT',
                'details' => $error ?: ($result === 'matched' ? 'Invoice receipt matches the bKash transaction.' : 'Invoice receipt does not match the bKash transaction.'),
                'checked_at' => $checkedAt,
            ]);
            PaymentAuditEvent::create([
                'invoice_payment_id' => $payment->id,
                'provider' => 'bkash',
                'event' => 'invoice_reconciliation_'.$result,
                'source' => 'admin_reconciliation',
                'after_state' => [
                    'gateway_status' => $gatewayStatus,
                    'gateway_amount' => $gatewayAmount,
                    'currency' => $currency,
                    'gateway_transaction_id' => $gatewayTrx,
                    'result' => $result,
                ],
            ]);
            return $record;
        });
    }

    public function reconcile(Payment $payment): PaymentReconciliation
    {
        $checkedAt = now();
        $verified = [];
        $error = null;

        try {
            if ($payment->gateway_provider !== 'bkash' || ! $payment->gateway_payment_id) {
                throw new \RuntimeException('Payment has no bKash gateway payment ID.');
            }
            $client = app(BkashCheckoutService::class);
            $verified = $client->queryPayment($client->grantToken(), $payment->gateway_payment_id);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $gatewayStatus = strtolower((string) ($verified['transactionStatus'] ?? ''));
        $gatewayAmount = isset($verified['amount']) ? round((float) $verified['amount'], 2) : null;
        $gatewayId = (string) ($verified['paymentID'] ?? '');
        $gatewayTrx = (string) ($verified['trxID'] ?? '');
        $currency = strtoupper((string) ($verified['currency'] ?? ''));
        $matches = $error === null && $payment->status === 'approved' && $gatewayStatus === 'completed'
            && $gatewayTrx !== ''
            && $gatewayId === $payment->gateway_payment_id
            && $gatewayAmount !== null && $gatewayAmount === round((float) $payment->amount, 2)
            && $currency === strtoupper((string) $payment->currency)
            && ($payment->transaction_reference === null || ($verified['merchantInvoiceNumber'] ?? null) === $payment->transaction_reference)
            && ($payment->gateway_transaction_id === null || $payment->gateway_transaction_id === $gatewayTrx);
        $result = $error !== null ? 'error' : ($matches ? 'matched' : 'mismatch');

        return DB::transaction(function () use ($payment, $checkedAt, $gatewayStatus, $gatewayAmount, $gatewayTrx, $currency, $result, $error): PaymentReconciliation {
            $record = PaymentReconciliation::create([
                'payment_id' => $payment->id, 'provider' => 'bkash',
                'gateway_payment_id' => $payment->gateway_payment_id,
                'gateway_transaction_id' => $gatewayTrx ?: $payment->gateway_transaction_id,
                'result' => $result, 'local_status' => $payment->status,
                'gateway_status' => $gatewayStatus ?: null, 'local_amount' => $payment->amount,
                'gateway_amount' => $gatewayAmount, 'currency' => $currency ?: $payment->currency,
                'details' => $error ?: ($result === 'matched' ? 'Gateway and LMS records match.' : 'Gateway response does not match the local payment.'),
                'checked_at' => $checkedAt,
            ]);
            PaymentAuditEvent::create([
                'payment_id' => $payment->id, 'provider' => 'bkash',
                'event' => 'reconciliation_'.$result, 'source' => 'admin_reconciliation',
                'after_state' => ['gateway_status' => $gatewayStatus, 'gateway_amount' => $gatewayAmount, 'currency' => $currency, 'gateway_transaction_id' => $gatewayTrx, 'result' => $result],
            ]);
            return $record;
        });
    }
}
