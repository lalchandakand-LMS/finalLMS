<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FlashSale;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Payments\BkashCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class StudentCheckoutController extends Controller
{
    public function options(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->isStudent(), 403);
        abort_unless($course->status === 'published', 404);

        $batches = $course->batches()
            ->where('status', 'open')
            ->orderBy('starts_at')
            ->get();

        $methods = collect(Setting::paymentMethods())
            ->filter(fn (array $method) => (bool) ($method['enabled'] ?? false))
            ->map(fn (array $method, string $key) => [
                'key' => $key,
                'label' => (string) ($method['label'] ?? $key),
                'account' => (string) ($method['account'] ?? ''),
                'instructions' => (string) ($method['instructions'] ?? ''),
            ])->values();

        $sale = FlashSale::activeForCourse($course->id, $request->user()->id);
        $pendingPayment = Payment::query()
            ->where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->latest()
            ->first();
        $baseAmount = (float) $course->price;
        $discountedAmount = $sale ? $sale->discountedPrice($baseAmount) : $baseAmount;

        return response()->json([
            'data' => [
                'course' => [
                    'id' => $course->id,
                    'slug' => $course->slug,
                    'title' => $course->title,
                    'price' => number_format($baseAmount, 2, '.', ''),
                ],
                'batches' => $batches->map(function (Batch $batch) use ($course, $sale): array {
                    $baseAmount = (float) $batch->fee > 0 ? (float) $batch->fee : (float) $course->price;
                    $discountedAmount = $sale ? $sale->discountedPrice($baseAmount) : $baseAmount;
                    $occupied = Enrollment::query()
                        ->where('batch_id', $batch->id)
                        ->whereIn('status', ['active', 'completed'])
                        ->count();
                    $pending = Payment::query()->where('batch_id', $batch->id)->where('status', 'pending')->count();

                    return [
                        'id' => $batch->id,
                        'title' => $batch->title,
                        'starts_at' => $batch->starts_at?->toIso8601String(),
                        'ends_at' => $batch->ends_at?->toIso8601String(),
                        'fee' => number_format((float) $batch->fee, 2, '.', ''),
                        'base_amount' => number_format($baseAmount, 2, '.', ''),
                        'discounted_amount' => number_format($discountedAmount, 2, '.', ''),
                        'discount_amount' => number_format(max(0, $baseAmount - $discountedAmount), 2, '.', ''),
                        'flash_sale_applied' => $sale !== null,
                        'capacity' => $batch->capacity,
                        'occupied' => $occupied + $pending,
                        'available_seats' => max(0, $batch->capacity - $occupied - $pending),
                    ];
                })->values(),
                'payment_methods' => $methods,
                'online_bkash_available' => $this->isBkashConfigured(),
                'pricing' => [
                    'base_amount' => number_format($baseAmount, 2, '.', ''),
                    'discounted_amount' => number_format($discountedAmount, 2, '.', ''),
                    'discount_amount' => number_format(max(0, $baseAmount - $discountedAmount), 2, '.', ''),
                    'flash_sale_applied' => $sale !== null,
                ],
                'already_enrolled' => $course->enrollments()
                    ->where('user_id', $request->user()->id)
                    ->exists(),
                'pending_payment' => $pendingPayment ? [
                    'id' => $pendingPayment->id,
                    'amount' => $pendingPayment->amount,
                    'method' => $pendingPayment->method,
                    'status' => $pendingPayment->status,
                    'transaction_reference' => $pendingPayment->transaction_reference,
                    'created_at' => $pendingPayment->created_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    public function submitManual(Request $request, Course $course): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless($course->status === 'published', 404);

        $enabledMethods = collect(Setting::paymentMethods())
            ->filter(fn (array $method) => (bool) ($method['enabled'] ?? false))
            ->keys()
            ->all();

        $hasOpenBatches = $course->batches()->where('status', 'open')->exists();
        $validated = $request->validate([
            'batch_id' => [
                $hasOpenBatches ? 'required' : 'nullable',
                'integer',
                Rule::exists('batches', 'id')->where(fn ($query) => $query
                    ->where('course_id', $course->id)
                    ->where('status', 'open')),
            ],
            'payment_method' => ['nullable', 'string', Rule::in($enabledMethods)],
            'sender_account' => ['nullable', 'string', 'max:50'],
            'transaction_reference' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn () => filled($request->input('payment_method'))),
                Rule::unique('payments', 'transaction_reference')
                    ->where(fn ($query) => $query->where('method', $request->input('payment_method'))),
            ],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $proof = $request->file('proof');
        $proofPath = null;

        try {
            $result = DB::transaction(function () use ($course, $student, $validated, $proof, $enabledMethods, &$proofPath): array {
                $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
                abort_unless($lockedCourse->status === 'published', 404);

                if (Enrollment::query()->where('course_id', $lockedCourse->id)->where('user_id', $student->id)->exists()) {
                    return ['status' => 'already_enrolled'];
                }

                if (Payment::query()->where('course_id', $lockedCourse->id)
                    ->where('user_id', $student->id)->where('status', 'pending')->exists()) {
                    return ['status' => 'payment_pending'];
                }

                $batchId = $validated['batch_id'] ?? null;
                $batch = null;
                if ($batchId) {
                    $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
                    if ((int) $batch->course_id !== (int) $lockedCourse->id
                        || $batch->status !== 'open'
                        || ! $this->hasAvailableSeat($batch)) {
                        return ['status' => 'batch_full'];
                    }
                }

                $sale = FlashSale::activeForCourse($lockedCourse->id, $student->id);
                if ($sale) {
                    $sale = FlashSale::query()->lockForUpdate()->find($sale->id);
                    if (! $sale || ! $sale->targetsCourse($lockedCourse->id) || ! $sale->isAvailableFor($student->id)) {
                        return ['status' => 'sale_unavailable'];
                    }
                }

                $baseAmount = $batch && (float) $batch->fee > 0
                    ? (float) $batch->fee
                    : (float) $lockedCourse->price;
                $amount = $sale ? $sale->discountedPrice($baseAmount) : $baseAmount;

                if ($amount <= 0) {
                    Enrollment::firstOrCreate(
                        ['user_id' => $student->id, 'course_id' => $lockedCourse->id],
                        ['batch_id' => $batchId, 'flash_sale_id' => $sale?->id, 'status' => 'active']
                    );

                    return ['status' => 'enrolled_free'];
                }

                if (! in_array($validated['payment_method'] ?? null, $enabledMethods, true)
                    || blank($validated['transaction_reference'] ?? null)) {
                    return ['status' => 'payment_details_required'];
                }

                $proofPath = $proof?->store('payment-proofs', 'local');
                $payment = Payment::create([
                    'user_id' => $student->id,
                    'course_id' => $lockedCourse->id,
                    'flash_sale_id' => $sale?->id,
                    'batch_id' => $batchId,
                    'method' => $validated['payment_method'],
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => 'BDT',
                    'sender_account' => $validated['sender_account'] ?? null,
                    'transaction_reference' => $validated['transaction_reference'],
                    'proof_path' => $proofPath,
                    'status' => 'pending',
                ]);

                return [
                    'status' => 'submitted',
                    'payment' => [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'method' => $payment->method,
                        'status' => $payment->status,
                        'transaction_reference' => $payment->transaction_reference,
                        'created_at' => $payment->created_at?->toIso8601String(),
                    ],
                ];
            });
        } catch (Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }

        if (($result['status'] ?? null) !== 'submitted' && $proofPath) {
            Storage::disk('local')->delete($proofPath);
        }

        return match ($result['status']) {
            'submitted' => response()->json(['message' => 'Payment submitted and is awaiting academy verification.', 'data' => $result['payment']], 201),
            'enrolled_free' => response()->json(['message' => 'Free course enrollment completed.', 'data' => ['status' => 'active']], 201),
            'already_enrolled' => response()->json(['message' => 'You are already enrolled in this course.'], 409),
            'payment_pending' => response()->json(['message' => 'A payment is already waiting for review.'], 409),
            'payment_details_required' => response()->json(['message' => 'Choose an enabled payment method and provide its transaction reference.'], 422),
            'batch_full' => response()->json(['message' => 'The selected batch is full or no longer available.'], 409),
            'sale_unavailable' => response()->json(['message' => 'The Flash Sale is no longer available. Refresh checkout and try again.'], 409),
            default => response()->json(['message' => 'The course is no longer available.'], 409),
        };
    }

    public function initiateBkash(Request $request, Course $course, BkashCheckoutService $bkash): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless($course->status === 'published', 404);
        abort_unless($this->isBkashConfigured(), 503, 'Online bKash checkout is not configured.');

        $hasOpenBatches = $course->batches()->where('status', 'open')->exists();
        $validated = $request->validate([
            'batch_id' => [
                $hasOpenBatches ? 'required' : 'nullable',
                'integer',
                Rule::exists('batches', 'id')->where(fn ($query) => $query
                    ->where('course_id', $course->id)
                    ->where('status', 'open')),
            ],
        ]);

        $payment = DB::transaction(function () use ($course, $student, $validated): Payment {
            $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
            abort_unless($lockedCourse->status === 'published', 404);

            if (Enrollment::query()->where('course_id', $lockedCourse->id)->where('user_id', $student->id)->exists()) {
                abort(409, 'You are already enrolled in this course.');
            }
            if (Payment::query()->where('course_id', $lockedCourse->id)
                ->where('user_id', $student->id)->where('status', 'pending')->exists()) {
                abort(409, 'A payment is already awaiting verification.');
            }

            $batchId = $validated['batch_id'] ?? null;
            $batch = null;
            if ($batchId) {
                $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
                if ((int) $batch->course_id !== (int) $lockedCourse->id
                    || $batch->status !== 'open'
                    || ! $this->hasAvailableSeat($batch)) {
                    abort(409, 'This batch has reached its enrollment capacity.');
                }
            }

            $sale = FlashSale::activeForCourse($lockedCourse->id, $student->id);
            if ($sale) {
                $sale = FlashSale::query()->lockForUpdate()->find($sale->id);
                abort_unless($sale && $sale->targetsCourse($lockedCourse->id) && $sale->isAvailableFor($student->id), 409,
                    'This Flash Sale has ended or reached its purchase limit.');
            }

            $baseAmount = $batch && (float) $batch->fee > 0
                ? (float) $batch->fee
                : (float) $lockedCourse->price;
            $amount = $sale ? $sale->discountedPrice($baseAmount) : $baseAmount;
            abort_if($amount <= 0, 422, 'Online checkout is not needed for a free course.');

            return Payment::create([
                'user_id' => $student->id,
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
                (string) $student->id
            );

            $paymentId = $created['paymentID'] ?? null;
            $checkoutUrl = $created['bkashURL'] ?? null;
            $checkoutHost = is_string($checkoutUrl) ? parse_url($checkoutUrl, PHP_URL_HOST) : null;
            $allowedHosts = ['tokenized.sandbox.bka.sh', 'tokenized.pay.bka.sh', 'checkout.sandbox.bka.sh', 'checkout.pay.bka.sh'];

            if (($created['statusCode'] ?? null) !== '0000'
                || ! is_string($paymentId) || $paymentId === ''
                || ! is_string($checkoutUrl)
                || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https'
                || ! in_array($checkoutHost, $allowedHosts, true)) {
                throw new \RuntimeException('bKash returned an invalid checkout response.');
            }

            $payment->update(['gateway_payment_id' => $paymentId]);

            return response()->json([
                'data' => [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => 'BDT',
                    'checkout_url' => $checkoutUrl,
                    'status' => 'pending',
                ],
            ], 201);
        } catch (Throwable) {
            DB::transaction(function () use ($payment): void {
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if ($lockedPayment->status === 'pending') {
                    $lockedPayment->update([
                        'status' => 'rejected',
                        'admin_note' => 'bKash checkout could not be initialized. The student may retry.',
                    ]);
                }
            });

            return response()->json(['message' => 'bKash checkout could not be started. Please try again.'], 502);
        }
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless($request->user()->isStudent(), 403);
        $payments = Payment::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('course_id')
            ->with('course:id,title,slug')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $payments->getCollection()->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'course' => $payment->course ? [
                    'id' => $payment->course->id,
                    'title' => $payment->course->title,
                    'slug' => $payment->course->slug,
                ] : null,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method,
                'status' => $payment->status,
                'transaction_reference' => $payment->transaction_reference,
                'created_at' => $payment->created_at?->toIso8601String(),
                'reviewed_at' => $payment->reviewed_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function showPayment(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($request->user()->isStudent() && (int) $payment->user_id === (int) $request->user()->id, 404);
        abort_unless($payment->course_id !== null, 404);
        $payment->load('course:id,title,slug');

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'course' => $payment->course ? [
                    'id' => $payment->course->id,
                    'title' => $payment->course->title,
                    'slug' => $payment->course->slug,
                ] : null,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method,
                'status' => $payment->status,
                'transaction_reference' => $payment->transaction_reference,
                'sender_account' => $payment->sender_account,
                'admin_note' => $payment->status === 'rejected' ? $payment->admin_note : null,
                'created_at' => $payment->created_at?->toIso8601String(),
                'reviewed_at' => $payment->reviewed_at?->toIso8601String(),
            ],
        ]);
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

    private function isBkashConfigured(): bool
    {
        return (bool) config('services.bkash.enabled')
            && filled(config('services.bkash.base_url'))
            && filled(config('services.bkash.username'))
            && filled(config('services.bkash.password'))
            && filled(config('services.bkash.app_key'))
            && filled(config('services.bkash.app_secret'));
    }
}
