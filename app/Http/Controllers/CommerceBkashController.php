<?php

namespace App\Http\Controllers;

use App\Models\CommercePaymentTransaction;
use App\Models\Order;
use App\Services\Commerce\OrderFulfillmentService;
use App\Services\Payments\BkashCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CommerceBkashController extends Controller
{
    public function initiate(Order $order, Request $request, BkashCheckoutService $bkash): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->status === 'pending', 409, 'Only pending orders can be paid.');
        abort_unless($order->payment_method === 'bkash', 422, 'This order was not created for bKash checkout.');
        abort_unless($this->isConfigured(), 503, 'bKash checkout is not configured.');
        abort_if((float) $order->total <= 0, 422, 'A zero-value order does not require online payment.');

        $transaction = DB::transaction(function () use ($order): CommercePaymentTransaction {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($lockedOrder->status === 'pending' && $lockedOrder->payment_method === 'bkash', 409);

            $existing = CommercePaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->where('provider', 'bkash')
                ->whereIn('status', ['initiated', 'pending'])
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return CommercePaymentTransaction::create([
                'order_id' => $lockedOrder->id,
                'provider' => 'bkash',
                'invoice' => 'ALM-ORDER-' . $lockedOrder->order_number . '-' . Str::upper(Str::random(6)),
                'amount' => number_format((float) $lockedOrder->total, 2, '.', ''),
                'currency' => 'BDT',
                'status' => 'initiated',
            ]);
        });

        if ($transaction->gateway_payment_id) {
            return back()->with('info', 'A bKash checkout is already in progress. Complete it before retrying.');
        }

        try {
            $token = $bkash->grantToken();
            $created = $bkash->createPayment(
                $token,
                number_format((float) $transaction->amount, 2, '.', ''),
                $transaction->invoice,
                route('store.payments.bkash.callback'),
                (string) $request->user()->id
            );

            $paymentId = $created['paymentID'] ?? null;
            $checkoutUrl = $created['bkashURL'] ?? null;
            $host = is_string($checkoutUrl) ? parse_url($checkoutUrl, PHP_URL_HOST) : null;
            $allowedHosts = ['tokenized.sandbox.bka.sh', 'tokenized.pay.bka.sh', 'checkout.sandbox.bka.sh', 'checkout.pay.bka.sh'];

            if (($created['statusCode'] ?? null) !== '0000'
                || !is_string($paymentId) || $paymentId === ''
                || !is_string($checkoutUrl)
                || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https'
                || !in_array($host, $allowedHosts, true)) {
                throw new \RuntimeException('Invalid bKash checkout response.');
            }

            $transaction->update([
                'gateway_payment_id' => $paymentId,
                'status' => 'pending',
                'gateway_response' => ['create' => $this->safeResponse($created)],
            ]);

            return redirect()->away($checkoutUrl);
        } catch (Throwable) {
            $transaction->update(['status' => 'failed']);
            return back()->with('error', 'bKash checkout could not be started. Please retry or contact the academy.');
        }
    }

    public function callback(Request $request, BkashCheckoutService $bkash, OrderFulfillmentService $fulfillment): RedirectResponse
    {
        $paymentId = $request->query('paymentID');
        $callbackStatus = strtolower((string) $request->query('status'));

        if (!is_string($paymentId) || $paymentId === '') {
            return redirect()->route('landing')->with('error', 'The bKash callback did not include a payment reference.');
        }

        $transaction = CommercePaymentTransaction::query()
            ->where('provider', 'bkash')
            ->where('gateway_payment_id', $paymentId)
            ->with('order')
            ->firstOrFail();
        $order = $transaction->order;

        if ($transaction->status === 'verified') {
            $result = $fulfillment->fulfill($order);
            return redirect()->route('store.orders')->with(
                $result === 'approved' || $result === 'reviewed' ? 'success' : 'warning',
                $result === 'approved' || $result === 'reviewed'
                    ? 'Your bKash payment is verified and your order is fulfilled.'
                    : 'Payment is verified, but this order needs administrator review.'
            );
        }

        try {
            $token = $bkash->grantToken();
            if ($callbackStatus === 'success') {
                try {
                    $bkash->executePayment($token, $paymentId);
                } catch (Throwable) {
                    // Query status is authoritative if execute was already processed.
                }
            }
            // Never trust the browser callback status: a cancelled/forged return can
            // arrive after the gateway has actually completed the charge.
            $verified = $bkash->queryPayment($token, $paymentId);

            $verifiedStatus = strtolower((string) ($verified['transactionStatus'] ?? ''));
            if ($verifiedStatus !== 'completed') {
                if (in_array($verifiedStatus, ['failed', 'cancelled', 'canceled', 'expired'], true)) {
                    DB::transaction(function () use ($transaction): void {
                        $locked = CommercePaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
                        if (in_array($locked->status, ['initiated', 'pending'], true)) {
                            $locked->update(['status' => 'failed']);
                        }
                    });
                    return redirect()->route('store.orders')->with('error', 'The bKash payment was not completed. You may retry from My Orders.');
                }

                return redirect()->route('store.orders')->with('warning', 'bKash is still processing this payment. Do not pay again; check My Orders shortly.');
            }
            $verifiedPaymentId = (string) ($verified['paymentID'] ?? '');
            $amount = round((float) ($verified['amount'] ?? -1), 2);
            $currency = strtoupper((string) ($verified['currency'] ?? 'BDT'));
            $invoice = (string) ($verified['merchantInvoiceNumber'] ?? '');
            $trxId = $verified['trxID'] ?? null;

            if ($verifiedStatus !== 'completed'
                || $verifiedPaymentId !== $paymentId
                || $amount !== round((float) $transaction->amount, 2)
                || $amount !== round((float) $order->total, 2)
                || $currency !== 'BDT'
                || strtoupper((string) $transaction->currency) !== 'BDT'
                || strtoupper((string) $order->currency) !== 'BDT'
                || $invoice !== $transaction->invoice
                || !is_string($trxId) || $trxId === '') {
                throw new \RuntimeException('bKash verification did not match the local order transaction.');
            }

            DB::transaction(function () use ($transaction, $verified, $trxId): void {
                $locked = CommercePaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
                if ($locked->status === 'verified') {
                    return;
                }
                abort_unless(in_array($locked->status, ['initiated', 'pending'], true), 409);
                $locked->update([
                    'gateway_transaction_id' => $trxId,
                    'status' => 'verified',
                    'gateway_response' => ['verification' => $this->safeResponse($verified)],
                    'verified_at' => now(),
                ]);
            });

            $result = $fulfillment->fulfill($order);
            if ($result === 'approved' || $result === 'reviewed') {
                return redirect()->route('store.orders')->with('success', 'bKash payment verified. Your order access is now active.');
            }

            return redirect()->route('store.orders')->with('warning', 'Payment was verified, but order fulfillment needs administrator review. Do not pay again.');
        } catch (Throwable) {
            return redirect()->route('store.orders')->with('warning', 'Your bKash payment is awaiting server-side verification. Do not pay again; contact the academy if this message remains.');
        }
    }

    private function isConfigured(): bool
    {
        return (bool) config('services.bkash.enabled')
            && filled(config('services.bkash.base_url'))
            && filled(config('services.bkash.username'))
            && filled(config('services.bkash.password'))
            && filled(config('services.bkash.app_key'))
            && filled(config('services.bkash.app_secret'));
    }

    private function safeResponse(array $response): array
    {
        unset($response['id_token'], $response['refresh_token'], $response['access_token']);
        return $response;
    }
}
