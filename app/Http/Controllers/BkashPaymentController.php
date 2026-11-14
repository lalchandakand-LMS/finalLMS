<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\DownloadPermission;
use App\Models\FlashSale;
use App\Services\Payments\BkashCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $hasOpenBatches = $course->batches()->where('status', 'open')->exists();
        $validated = $request->validate([
            'batch_id' => [
                $hasOpenBatches ? 'required' : 'nullable',
                'integer',
                Rule::exists('batches', 'id')->where(fn ($query) => $query->where('course_id', $course->id)->where('status', 'open')),
            ],
        ]);
        $batchId = $validated['batch_id'] ?? null;

        $payment = DB::transaction(function () use ($batchId, $course, $user): Payment {
            $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
            abort_unless($lockedCourse->status === 'published', 404);

            if (Enrollment::query()->where('course_id', $lockedCourse->id)->where('user_id', $user->id)->exists()) {
                abort(409, 'You are already enrolled in this course.');
            }
            if (Payment::query()->where('course_id', $lockedCourse->id)->where('user_id', $user->id)->where('status', 'pending')->exists()) {
                abort(409, 'A payment is already awaiting verification.');
            }

            if ($batchId) {
                $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
                if ($batch->course_id !== $lockedCourse->id || $batch->status !== 'open' || ! $this->hasAvailableSeat($batch)) {
                    abort(409, 'This batch has reached its enrollment capacity.');
                }
            } else {
                $batch = null;
            }

            $sale = FlashSale::activeForCourse($lockedCourse->id, $user->id);
            if ($sale) {
                $sale = FlashSale::query()->lockForUpdate()->find($sale->id);
                abort_unless($sale && $sale->courses()->whereKey($lockedCourse->id)->exists() && $sale->isAvailableFor($user->id), 409, 'This Flash Sale has ended or reached its purchase limit.');
            }

            $baseAmount = $batch && (float) $batch->fee > 0 ? (float) $batch->fee : (float) $lockedCourse->price;
            $amount = $sale ? $sale->discountedPrice($baseAmount) : $baseAmount;
            abort_if($amount <= 0, 422, 'Online checkout is not needed for a free course.');

            return Payment::create([
                'user_id' => $user->id,
                'course_id' => $lockedCourse->id,
                'flash_sale_id' => $sale?->id,
                'batch_id' => $batchId,
                'method' => 'bkash',
                'gateway_provider' => 'bkash',
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
                number_format((float) $payment->amount, 2, '.', ''),
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

    public function initiateEbook(Product $product, Request $request, BkashCheckoutService $bkash): RedirectResponse
    {
        abort_unless($request->user()?->isStudent(), 403);
        abort_unless($this->isConfigured(), 503, 'bKash checkout is not configured.');

        $student = $request->user();
        $payment = DB::transaction(function () use ($product, $student): Payment {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedProduct->type === 'ebook' && $lockedProduct->is_downloadable
                && $lockedProduct->status === 'published' && $lockedProduct->published_at
                && $lockedProduct->published_at->lte(now()) && $lockedProduct->download_path
                && Storage::disk('local')->exists($lockedProduct->download_path), 422,
                'This eBook is not currently available.');

            $alreadyPending = Order::query()->where('user_id', $student->id)
                ->where('status', 'pending')
                ->whereHas('items', fn ($query) => $query->where('product_id', $lockedProduct->id))
                ->exists();
            abort_if($alreadyPending, 409, 'You already have a pending order for this eBook.');

            $alreadyOwned = DownloadPermission::query()
                ->where('user_id', $student->id)
                ->where('product_id', $lockedProduct->id)
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($query) => $query->whereNull('downloads_remaining')->orWhere('downloads_remaining', '>', 0))
                ->whereHas('orderItem.order', fn ($query) => $query->where('status', 'completed'))
                ->exists();
            abort_if($alreadyOwned, 409, 'This eBook is already available in your library.');

            $amount = (float) $lockedProduct->currentPrice();
            abort_if($amount <= 0, 422, 'Free eBooks do not require bKash checkout.');

            $invoice = 'BKASH-'.Str::upper(Str::random(20));
            $order = Order::create([
                'order_number' => 'AEC-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'user_id' => $student->id,
                'status' => 'pending',
                'payment_method' => 'bkash',
                'payment_reference' => $invoice,
                'currency' => 'BDT',
                'subtotal' => $amount,
                'discount_total' => 0,
                'total' => $amount,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $lockedProduct->id,
                'product_type' => 'ebook',
                'product_name' => $lockedProduct->name,
                'quantity' => 1,
                'regular_price' => $amount,
                'line_total' => $amount,
                'product_snapshot' => [
                    'name' => $lockedProduct->name,
                    'slug' => $lockedProduct->slug,
                    'download_path' => $lockedProduct->download_path,
                ],
            ]);

            return Payment::create([
                'user_id' => $student->id,
                'course_id' => null,
                'order_id' => $order->id,
                'method' => 'bkash',
                'gateway_provider' => 'bkash',
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'BDT',
                'transaction_reference' => $invoice,
                'status' => 'pending',
            ]);
        });

        try {
            $token = $bkash->grantToken();
            $created = $bkash->createPayment(
                $token,
                number_format((float) $payment->amount, 2, '.', ''),
                $payment->transaction_reference,
                route('payments.bkash.callback'),
                (string) $student->id
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
            DB::transaction(function () use ($payment): void {
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if ($lockedPayment->status === 'pending') {
                    $lockedPayment->update([
                        'status' => 'rejected',
                        'admin_note' => 'bKash checkout could not be initialized. The student may retry.',
                    ]);
                    Order::query()->whereKey($lockedPayment->order_id)->where('status', 'pending')
                        ->update(['status' => 'cancelled']);
                }
            });

            return redirect()->route('ebooks.show', $product)
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
            return $payment->order_id
                ? redirect()->route('ebooks.orders')->with('success', 'Your eBook payment is already verified.')
                : redirect()->route('courses.show', $payment->course)->with('success', 'Your payment is already verified.');
        }

        // The browser callback query string is not proof of payment success or failure.
        // Always ask bKash for the authoritative server-side transaction status before
        // changing the local payment state. Only attempt execute on a success callback;
        // queryPayment remains mandatory in either case.
        try {
            $token = $bkash->grantToken();
            $executed = [];
            if ($callbackStatus === 'success') {
                try {
                    $executed = $bkash->executePayment($token, $paymentId);
                } catch (Throwable) {
                    // A repeated callback may arrive after bKash has already executed the payment.
                    // The payment-status query below remains the authoritative verification step.
                }
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

                if ($lockedPayment->order_id) {
                    $order = Order::query()->with('items.product')->lockForUpdate()->findOrFail($lockedPayment->order_id);
                    if ($order->status === 'completed') {
                        $lockedPayment->update([
                            'status' => 'approved',
                            'gateway_transaction_id' => $transactionId,
                            'reviewed_at' => now(),
                            'admin_note' => null,
                        ]);

                        return 'already';
                    }
                    if ($order->status !== 'pending') {
                        return 'closed';
                    }

                    $items = $order->items;
                    $validOrder = (int) $order->user_id === (int) $lockedPayment->user_id
                        && $order->payment_reference === $lockedPayment->transaction_reference
                        && abs((float) $order->total - (float) $lockedPayment->amount) < 0.01
                        && $items->isNotEmpty()
                        && $items->every(fn (OrderItem $item): bool => $item->product_type === 'ebook'
                            && (int) $item->quantity === 1
                            && $item->product
                            && $item->product->is_downloadable
                            && $item->product->download_path
                            && Storage::disk('local')->exists($item->product->download_path));

                    if (! $validOrder) {
                        $lockedPayment->update([
                            'status' => 'rejected',
                            'gateway_transaction_id' => $transactionId,
                            'reviewed_at' => now(),
                            'admin_note' => 'bKash payment succeeded but the eBook order could not be fulfilled. Manual refund is required.',
                        ]);
                        $order->update(['status' => 'cancelled']);

                        return 'ebook_unavailable';
                    }

                    foreach ($items as $item) {
                        DownloadPermission::firstOrCreate(
                            ['order_item_id' => $item->id],
                            [
                                'user_id' => $lockedPayment->user_id,
                                'product_id' => $item->product_id,
                                'downloads_remaining' => $item->product->download_limit,
                                'expires_at' => $item->product->download_expires_at,
                            ]
                        );
                    }

                    $order->update(['status' => 'completed', 'paid_at' => now(), 'completed_at' => now()]);
                    $lockedPayment->update([
                        'status' => 'approved',
                        'gateway_transaction_id' => $transactionId,
                        'reviewed_at' => now(),
                        'admin_note' => null,
                    ]);

                    return 'ebook_approved';
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

            if ($payment->order_id) {
                return match ($result) {
                    'ebook_approved', 'already' => redirect()->route('ebooks.orders')
                        ->with('success', 'bKash payment verified. Your eBook is now available in your library.'),
                    'ebook_unavailable' => redirect()->route('ebooks.orders')
                        ->with('error', 'bKash payment succeeded, but the eBook could not be fulfilled. The academy must arrange a manual refund.'),
                    default => redirect()->route('ebooks.orders')
                        ->with('error', 'This eBook payment is no longer eligible for automatic fulfillment. Please contact the academy.'),
                };
            }

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
            return $payment->order_id
                ? redirect()->route('ebooks.orders')->with('warning', 'Your bKash payment is awaiting server-side verification. Do not pay again; contact the academy if this message remains.')
                : redirect()->route('courses.show', $payment->course)->with('warning', 'Your bKash payment is awaiting server-side verification. Do not pay again; contact the academy if this message remains.');
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
