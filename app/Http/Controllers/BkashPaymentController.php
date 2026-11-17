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
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class BkashPaymentController extends Controller
{
    public function initiate(Course $course, Request $request, PaymentGatewayManager $gateways): RedirectResponse
    {
        abort_unless($request->user()?->isStudent(), 403);
        abort_unless($course->status === 'published', 404);
        abort_unless($gateways->isAvailable('bkash'), 503, 'bKash checkout is not enabled or configured.');

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
                abort_unless($sale && $sale->targetsCourse($lockedCourse->id) && $sale->isAvailableFor($user->id), 409, 'This Flash Sale has ended or reached its purchase limit.');
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
            $checkout = $gateways->driver('bkash')->createCheckout(
                $payment,
                route('payments.bkash.callback'),
                (string) $payment->user_id
            );
            $payment->update(['gateway_payment_id' => $checkout['payment_id']]);

            return redirect()->away($checkout['checkout_url']);
        } catch (Throwable) {
            $payment->update([
                'status' => 'rejected',
                'admin_note' => 'bKash checkout could not be initialized. The student may retry.',
            ]);

            return redirect()->route('courses.show', $course)
                ->with('error', __('bKash checkout could not be started. Please try again or contact the academy.'));
        }
    }

    public function initiateEbook(Product $product, Request $request, PaymentGatewayManager $gateways): RedirectResponse
    {
        abort_unless($request->user()?->isStudent(), 403);
        abort_unless($gateways->isAvailable('bkash'), 503, 'bKash checkout is not enabled or configured.');

        $student = $request->user();
        $payment = DB::transaction(function () use ($product, $student): Payment {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedProduct->type === 'ebook' && $lockedProduct->is_downloadable
                && $lockedProduct->status === 'published' && $lockedProduct->published_at
                && $lockedProduct->published_at->lte(now()) && $lockedProduct->download_path
                && Storage::disk('local')->exists($lockedProduct->download_path), 422,
                __('This eBook is not currently available.'));

            $alreadyPending = Order::query()->where('user_id', $student->id)
                ->where('status', 'pending')
                ->whereHas('items', fn ($query) => $query->where('product_id', $lockedProduct->id))
                ->exists();
            abort_if($alreadyPending, 409, __('You already have a pending order for this eBook.'));

            $alreadyOwned = DownloadPermission::query()
                ->where('user_id', $student->id)
                ->where('product_id', $lockedProduct->id)
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($query) => $query->whereNull('downloads_remaining')->orWhere('downloads_remaining', '>', 0))
                ->whereHas('orderItem.order', fn ($query) => $query->where('status', 'completed'))
                ->exists();
            abort_if($alreadyOwned, 409, __('This eBook is already available in your library.'));

            abort_unless(strtoupper((string) ($lockedProduct->currency ?: 'BDT')) === 'BDT', 422, __('bKash supports BDT payments only.'));
            $amount = (float) $lockedProduct->currentPrice();
            abort_if($amount <= 0, 422, __('Free eBooks do not require bKash checkout.'));
            $regularAmount = (float) $lockedProduct->regular_price;
            $discountAmount = max(0, $regularAmount - $amount);

            $invoice = 'BKASH-'.Str::upper(Str::random(20));
            $order = Order::create([
                'order_number' => 'AEC-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'user_id' => $student->id,
                'status' => 'pending',
                'payment_method' => 'bkash',
                'payment_reference' => $invoice,
                'currency' => 'BDT',
                'subtotal' => $regularAmount,
                'discount_total' => $discountAmount,
                'total' => $amount,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $lockedProduct->id,
                'product_type' => 'ebook',
                'product_name' => $lockedProduct->name,
                'quantity' => 1,
                'regular_price' => $regularAmount,
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
            $checkout = $gateways->driver('bkash')->createCheckout(
                $payment,
                route('payments.bkash.callback'),
                (string) $payment->user_id
            );
            $payment->update(['gateway_payment_id' => $checkout['payment_id']]);

            return redirect()->away($checkout['checkout_url']);
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
                ->with('error', __('bKash checkout could not be started. Please try again or contact the academy.'));
        }
    }

    public function callback(Request $request, PaymentGatewayManager $gateways): RedirectResponse
    {
        $paymentId = $request->query('paymentID');
        if (! is_string($paymentId) || $paymentId === '') {
            return redirect()->route('landing')->with('error', 'The bKash callback did not include a payment reference.');
        }

        $payment = Payment::query()
            ->where('method', 'bkash')
            ->where('gateway_payment_id', $paymentId)
            ->firstOrFail();

        if ($payment->status === 'approved') {
            if ($payment->learn_earn_exam_id) {
                return redirect()->to('/student/learn-earn/exams')
                    ->with('success', __('Your paid exam access is already verified.'));
            }

            return $payment->order_id
                ? redirect()->route('ebooks.orders')->with('success', __('Your eBook payment is already verified.'))
                : redirect()->route('courses.show', $payment->course)->with('success', 'Your payment is already verified.');
        }

        // The browser callback query string is not proof of payment success or failure.
        // Always ask bKash for the authoritative server-side transaction status before
        // changing the local payment state. Only attempt execute on a success callback;
        // queryPayment remains mandatory in either case.
        try {
            $verification = $gateways->driver((string) $payment->gateway_provider)->verifyCallback($payment, $request->query());
            $verified = $verification['verified'];
            $executed = $verification['executed'];

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
                || ($invoice !== null && $invoice !== $payment->transaction_reference)
                || ($payment->order_id && $invoice !== $payment->transaction_reference)
                || ($payment->order_id && ! isset($verified['paymentID']))
                || ($payment->order_id && (! isset($verified['currency']) || strtoupper((string) $verified['currency']) !== 'BDT'))
                || ($payment->learn_earn_exam_id && ($invoice !== $payment->transaction_reference
                    || ! isset($verified['paymentID'])
                    || ! isset($verified['currency'])
                    || strtoupper((string) $verified['currency']) !== 'BDT'))) {
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

                if ($lockedPayment->learn_earn_exam_id) {
                    $exam = DB::table('learn_earn_exams')
                        ->where('id', $lockedPayment->learn_earn_exam_id)
                        ->where('status', 'published')
                        ->where('ends_at', '>=', now())
                        ->lockForUpdate()
                        ->first();
                    $studentRole = DB::table('users')->where('id', $lockedPayment->user_id)->value('role');

                    if (! $exam
                        || $exam->status !== 'published'
                        || strtotime((string) $exam->ends_at) < now()->timestamp
                        || $lockedPayment->course_id !== null
                        || $lockedPayment->order_id !== null
                        || $studentRole !== 'student'
                        || $exam->access_type !== 'paid'
                        || (float) $lockedPayment->amount <= 0
                        || strtoupper((string) $lockedPayment->currency) !== 'BDT'
                        || $lockedPayment->gateway_provider !== 'bkash') {
                        $lockedPayment->update([
                            'status' => 'rejected',
                            'gateway_transaction_id' => $transactionId,
                            'reviewed_at' => now(),
                            'admin_note' => 'bKash payment succeeded but the paid exam entitlement could not be validated. Manual refund or review is required.',
                        ]);

                        return 'learn_earn_exam_unavailable';
                    }

                    $entitlement = DB::table('learn_earn_exam_entitlements')
                        ->where('exam_id', $exam->id)
                        ->where('student_id', $lockedPayment->user_id)
                        ->lockForUpdate()
                        ->first();

                    if ($entitlement && $entitlement->status === 'active') {
                        $lockedPayment->update([
                            'status' => 'rejected',
                            'gateway_transaction_id' => $transactionId,
                            'reviewed_at' => now(),
                            'admin_note' => 'A paid exam entitlement already exists for this student. A duplicate successful payment requires manual refund.',
                        ]);

                        return 'learn_earn_exam_already_owned';
                    }

                    if ($entitlement) {
                        DB::table('learn_earn_exam_entitlements')->where('id', $entitlement->id)->update([
                            'payment_id' => $lockedPayment->id,
                            'status' => 'active',
                            'paid_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('learn_earn_exam_entitlements')->insert([
                            'exam_id' => $exam->id,
                            'student_id' => $lockedPayment->user_id,
                            'payment_id' => $lockedPayment->id,
                            'status' => 'active',
                            'paid_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $lockedPayment->update([
                        'status' => 'approved',
                        'gateway_transaction_id' => $transactionId,
                        'reviewed_at' => now(),
                        'admin_note' => null,
                    ]);

                    return 'learn_earn_exam_approved';
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

            if ($payment->learn_earn_exam_id) {
                return match ($result) {
                    'learn_earn_exam_approved', 'already' => redirect()->to('/student/learn-earn/exams')
                        ->with('success', __('bKash payment verified. Your paid exam access is now active.')),
                    'learn_earn_exam_already_owned' => redirect()->to('/student/learn-earn/exams')
                        ->with('error', __('An active entitlement already exists. The academy must review this duplicate payment for refund.')),
                    'learn_earn_exam_unavailable' => redirect()->to('/student/learn-earn/exams')
                        ->with('error', __('Payment succeeded but exam access could not be granted. The academy must review or refund this payment.')),
                    default => redirect()->to('/student/learn-earn/exams')
                        ->with('error', __('This paid exam payment is no longer eligible for automatic approval. Contact the academy.')),
                };
            }

            if ($payment->order_id) {
                return match ($result) {
                    'ebook_approved', 'already' => redirect()->route('ebooks.orders')
                        ->with('success', __('bKash payment verified. Your eBook is now available in your library.')),
                    'ebook_unavailable' => redirect()->route('ebooks.orders')
                        ->with('error', __('bKash payment succeeded, but the eBook could not be fulfilled. The academy must arrange a manual refund.')),
                    default => redirect()->route('ebooks.orders')
                        ->with('error', __('This eBook payment is no longer eligible for automatic fulfillment. Please contact the academy.')),
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
        } catch (Throwable $exception) {
            report($exception);

            // Keep diagnostics useful without recording callback query data, tokens,
            // payer details, or provider credentials.
            \Illuminate\Support\Facades\Log::warning('bKash callback verification failed.', [
                'payment_record_id' => $payment->id,
                'payment_intent' => $payment->learn_earn_exam_id ? 'learn_earn_exam' : ($payment->order_id ? 'ebook' : 'course'),
                'exception_class' => get_class($exception),
                'exception_message' => \Illuminate\Support\Str::limit($exception->getMessage(), 240),
            ]);

            if ($payment->learn_earn_exam_id) {
                return redirect()->to('/student/learn-earn/exams')
                    ->with('warning', __('Your bKash payment is awaiting server-side verification. Do not pay again; contact the academy if this message remains.'));
            }

            return $payment->order_id
                ? redirect()->route('ebooks.orders')->with('warning', __('Your bKash payment is awaiting server-side verification. Do not pay again; contact the academy if this message remains.'))
                : redirect()->route('courses.show', $payment->course)->with('warning', __('Your bKash payment is awaiting server-side verification. Do not pay again; contact the academy if this message remains.'));
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
