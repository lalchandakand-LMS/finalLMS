<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\Payments\BkashCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class BkashPaymentController extends Controller
{
    public function initiate(Course $course, Request $request, BkashCheckoutService $bkash): RedirectResponse
    {
        abort_unless($request->user()?->isStudent(), 403);
        abort_unless($course->status === 'published', 404);
        abort_unless($this->isConfigured(), 503, 'bKash checkout is not configured.');

        $user = $request->user();

        if (Enrollment::where('course_id', $course->id)->where('user_id', $user->id)->exists()) {
            return back()->with('info', 'You are already enrolled in this course.');
        }

        if (Payment::where('course_id', $course->id)->where('user_id', $user->id)->where('status', 'pending')->exists()) {
            return back()->with('info', 'A payment is already awaiting verification.');
        }

        $hasOpenBatches = $course->batches()->where('status', 'open')->exists();
        $validated = $request->validate([
            'batch_id' => [
                $hasOpenBatches ? 'required' : 'nullable',
                'integer',
                Rule::exists('batches', 'id')->where(fn ($query) => $query->where('course_id', $course->id)->where('status', 'open')),
            ],
        ]);

        $batchId = $validated['batch_id'] ?? null;
        $batchFee = $batchId ? (float) Batch::whereKey($batchId)->value('fee') : 0;
        $amount = $batchFee > 0 ? $batchFee : (float) $course->price;

        abort_if($amount <= 0, 422, 'Online checkout is not needed for a free course.');

        $payment = DB::transaction(function () use ($amount, $batchId, $course, $user): Payment {
            if ($batchId) {
                $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
                if ($batch->status !== 'open' || ! $this->hasAvailableSeat($batch)) {
                    abort(409, 'This batch has reached its enrollment capacity.');
                }
            }

            return Payment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'batch_id' => $batchId,
                'method' => 'bkash',
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'BDT',
                'transaction_reference' => 'BKASH-'.Str::upper(Str::random(20)),
                'status' => 'pending',
            ]);
        });

        try {
            $token = $bkash->grantToken();
            $created = $bkash->createPayment(
                $token,
                number_format($amount, 2, '.', ''),
                $payment->transaction_reference,
                route('payments.bkash.callback'),
                (string) $user->id
            );

            $paymentId = $created['paymentID'] ?? null;
            $checkoutUrl = $created['bkashURL'] ?? null;
            $checkoutHost = is_string($checkoutUrl) ? parse_url($checkoutUrl, PHP_URL_HOST) : null;
            $allowedHosts = ['tokenized.sandbox.bka.sh', 'tokenized.pay.bka.sh', 'checkout.sandbox.bka.sh', 'checkout.pay.bka.sh'];

            if (($created['statusCode'] ?? null) !== '0000'
                || ! is_string($paymentId)
                || $paymentId === ''
                || ! is_string($checkoutUrl)
                || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https'
                || ! in_array($checkoutHost, $allowedHosts, true)) {
                throw new \RuntimeException('bKash returned an invalid checkout response.');
            }

            $payment->update(['gateway_payment_id' => $paymentId]);

            return redirect()->away($checkoutUrl);
        } catch (Throwable) {
            $payment->update([
                'status' => 'rejected',
                'admin_note' => 'bKash checkout could not be initialized. The student may retry.',
            ]);

            return redirect()->route('courses.show', $course)
                ->with('error', 'bKash checkout could not be started. Please try again or contact the academy.');
        }
    }

    public function callback(Request $request, BkashCheckoutService $bkash): RedirectResponse
    {
        $paymentId = $request->query('paymentID');
        $callbackStatus = strtolower((string) $request->query('status'));

        if (! is_string($paymentId) || $paymentId === '') {
            return redirect()->route('landing')->with('error', 'The bKash callback did not include a payment reference.');
        }

        $payment = Payment::query()
            ->where('method', 'bkash')
            ->where('gateway_payment_id', $paymentId)
            ->firstOrFail();

        if ($payment->status === 'approved') {
            return redirect()->route('courses.show', $payment->course)->with('success', 'Your payment is already verified.');
        }

        if ($callbackStatus !== 'success') {
            $payment->update([
                'status' => 'rejected',
                'admin_note' => 'The bKash checkout was cancelled or not completed.',
            ]);

            return redirect()->route('courses.show', $payment->course)
                ->with('error', 'The bKash payment was not completed. You can try again.');
        }

        try {
            $token = $bkash->grantToken();
            $executed = [];
            try {
                $executed = $bkash->executePayment($token, $paymentId);
            } catch (Throwable) {
                // A repeated callback may arrive after bKash has already executed the payment.
                // The payment-status query below remains the authoritative verification step.
            }
            $verified = $bkash->queryPayment($token, $paymentId);

            $status = strtolower((string) ($verified['transactionStatus'] ?? ''));
            $verifiedPaymentId = (string) ($verified['paymentID'] ?? $paymentId);
            $verifiedAmount = round((float) ($verified['amount'] ?? -1), 2);
            $transactionId = $verified['trxID'] ?? null;
            $invoice = $verified['merchantInvoiceNumber'] ?? $executed['merchantInvoiceNumber'] ?? null;

            if ($status !== 'completed'
                || $verifiedPaymentId !== $paymentId
                || $verifiedAmount !== round((float) $payment->amount, 2)
                || (isset($verified['currency']) && strtoupper((string) $verified['currency']) !== 'BDT')
                || ! is_string($transactionId)
                || $transactionId === ''
                || ($invoice !== null && $invoice !== $payment->transaction_reference)) {
                throw new \RuntimeException('bKash transaction verification did not match the local payment.');
            }

            $result = DB::transaction(function () use ($payment, $transactionId): string {
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

                if ($lockedPayment->status === 'approved') {
                    return 'already';
                }

                if ($lockedPayment->status !== 'pending') {
                    return 'closed';
                }

                if ($lockedPayment->batch_id) {
                    $batch = Batch::query()->lockForUpdate()->findOrFail($lockedPayment->batch_id);
                    $enrollments = Enrollment::query()
                        ->where('batch_id', $batch->id)
                        ->whereIn('status', ['active', 'completed'])
                        ->count();
                    $otherPending = Payment::query()
                        ->where('batch_id', $batch->id)
                        ->where('status', 'pending')
                        ->where('id', '<>', $lockedPayment->id)
                        ->count();

                    if ($batch->status !== 'open' || $enrollments + $otherPending >= $batch->capacity) {
                        $lockedPayment->update([
                            'status' => 'rejected',
                            'gateway_transaction_id' => $transactionId,
                            'admin_note' => 'bKash payment succeeded, but the batch has no remaining seat. Manual refund or reassignment is required.',
                        ]);

                        return 'full';
                    }
                }

                $lockedPayment->update([
                    'status' => 'approved',
                    'gateway_transaction_id' => $transactionId,
                    'reviewed_at' => now(),
                    'admin_note' => null,
                ]);

                Enrollment::firstOrCreate(
                    ['user_id' => $lockedPayment->user_id, 'course_id' => $lockedPayment->course_id],
                    ['batch_id' => $lockedPayment->batch_id, 'status' => 'active']
                );

                return 'approved';
            });

            if ($result === 'full') {
                return redirect()->route('courses.show', $payment->course)
                    ->with('error', 'Your bKash payment succeeded, but the selected batch is full. Please contact the academy for a refund or another batch.');
            }

            if ($result === 'closed') {
                return redirect()->route('courses.show', $payment->course)
                    ->with('error', 'This payment is no longer eligible for automatic approval. Please contact the academy.');
            }

            return redirect()->route('courses.show', $payment->course)
                ->with('success', 'bKash payment verified. Your course enrollment is now active.');
        } catch (Throwable) {
            return redirect()->route('courses.show', $payment->course)
                ->with('warning', 'Your bKash payment is awaiting server-side verification. Do not pay again; contact the academy if this message remains.');
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

    private function hasAvailableSeat(Batch $batch): bool
    {
        $enrollments = Enrollment::query()
            ->where('batch_id', $batch->id)
            ->whereIn('status', ['active', 'completed'])
            ->count();
        $pendingPayments = Payment::query()
            ->where('batch_id', $batch->id)
            ->where('status', 'pending')
            ->count();

        return $enrollments + $pendingPayments < $batch->capacity;
    }
}
