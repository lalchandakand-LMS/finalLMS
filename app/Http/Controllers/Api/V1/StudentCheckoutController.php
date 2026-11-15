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
    public function quote(Request $request, Course $course): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless($course->status === 'published', 404);

        $batchId = $request->query('batch_id');
        $batch = null;
        if ($batchId !== null) {
            $batch = Batch::query()->whereKey($batchId)
                ->where('course_id', $course->id)->where('status', 'open')->firstOrFail();
        }

        $hasOpenBatches = $course->batches()->where('status', 'open')->exists();
        if ($batch) {
            abort_unless($this->hasAvailableSeat($batch), 409, 'The selected batch is full or unavailable.');
        }
        $openBatches = $course->batches()->where('status', 'open')
            ->orderBy('starts_at')
            ->get(['id', 'title', 'fee', 'capacity', 'starts_at', 'ends_at'])
            ->filter(fn (Batch $openBatch) => $this->hasAvailableSeat($openBatch))
            ->values();

        $base = $batch && (float) $batch->fee > 0 ? (float) $batch->fee : (float) $course->price;
        $sale = FlashSale::activeForCourse($course->id, $student->id);
        $amount = $sale ? $sale->discountedPrice($base) : $base;
        $methods = collect(Setting::paymentMethods())
            ->filter(fn (array $method, string $key) => (bool) ($method['enabled'] ?? false))
            ->map(fn (array $method, string $key) => [
                'key' => $key,
                'label' => $method['label'] ?? $key,
                'instructions' => $method['instructions'] ?? '',
                'account' => $key === 'bkash' ? null : ($method['account'] ?? null),
            ])->values();

        return response()->json([
            'course' => ['id' => $course->id, 'slug' => $course->slug, 'title' => $course->title],
            'batch' => $batch ? ['id' => $batch->id, 'title' => $batch->title, 'fee' => (float) $batch->fee] : null,
            'open_batches' => $openBatches->map(fn (Batch $openBatch) => [
                'id' => $openBatch->id,
                'title' => $openBatch->title,
                'fee' => (float) $openBatch->fee,
                'capacity' => (int) $openBatch->capacity,
                'starts_at' => $openBatch->starts_at?->toIso8601String(),
                'ends_at' => $openBatch->ends_at?->toIso8601String(),
            ])->values(),
            'batch_required' => $hasOpenBatches && ! $batch,
            'currency' => 'BDT',
            'base_amount' => round($base, 2),
            'discounted_amount' => round($amount, 2),
            'flash_sale_applied' => (bool) $sale,
            'is_free' => $amount <= 0,
            'payment_methods' => $methods,
            'already_enrolled' => Enrollment::query()->where('user_id', $student->id)
                ->where('course_id', $course->id)->whereIn('status', ['active', 'completed'])->exists(),
            'payment_pending' => Payment::query()->where('user_id', $student->id)
                ->where('course_id', $course->id)->where('status', 'pending')->exists(),
        ]);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless($course->status === 'published', 404);

        $hasOpenBatches = $course->batches()->where('status', 'open')->exists();
        $validated = $request->validate([
            'batch_id' => [
                $hasOpenBatches ? 'required' : 'nullable',
                'integer',
                Rule::exists('batches', 'id')->where(fn ($query) => $query
                    ->where('course_id', $course->id)->where('status', 'open')),
            ],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'sender_account' => ['nullable', 'string', 'max:50'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $batchId = $validated['batch_id'] ?? null;
        $enabledMethods = collect(Setting::paymentMethods())
            ->filter(fn (array $method) => (bool) ($method['enabled'] ?? false))
            ->keys()->all();
        $method = $validated['payment_method'] ?? null;
        if ($method !== null) {
            abort_unless(in_array($method, $enabledMethods, true) && $method !== 'bkash', 422,
                'Choose an enabled manual payment method. Use the bKash checkout endpoint for bKash.');
            $request->validate([
                'transaction_reference' => ['required', 'string', 'max:120',
                    Rule::unique('payments', 'transaction_reference')
                        ->where(fn ($query) => $query->where('method', $method))],
            ]);
        }

        $proof = $request->file('proof');
        $proofPath = null;
        if ($proof) {
            $proofPath = $proof->store('payment-proofs', 'local');
            abort_unless($proofPath, 500, 'The payment proof could not be stored.');
        }

        try {
            $result = DB::transaction(function () use ($course, $student, $batchId, $method, $validated, $proofPath): array {
                $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
                abort_unless($lockedCourse->status === 'published', 404);

                if (Enrollment::query()->where('course_id', $lockedCourse->id)
                    ->where('user_id', $student->id)->whereIn('status', ['active', 'completed'])->exists()) {
                    return ['state' => 'already_enrolled'];
                }
                if (Payment::query()->where('course_id', $lockedCourse->id)
                    ->where('user_id', $student->id)->where('status', 'pending')->exists()) {
                    return ['state' => 'payment_pending'];
                }

                $batch = null;
                if ($batchId) {
                    $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
                    if ($batch->course_id !== $lockedCourse->id || $batch->status !== 'open' || ! $this->hasAvailableSeat($batch)) {
                        return ['state' => 'batch_unavailable'];
                    }
                } elseif ($lockedCourse->batches()->where('status', 'open')->exists()) {
                    return ['state' => 'batch_required'];
                }

                $sale = FlashSale::activeForCourse($lockedCourse->id, $student->id);
                if ($sale) {
                    $sale = FlashSale::query()->lockForUpdate()->find($sale->id);
                    if (! $sale || ! $sale->targetsCourse($lockedCourse->id) || ! $sale->isAvailableFor($student->id)) {
                        return ['state' => 'sale_unavailable'];
                    }
                }

                $base = $batch && (float) $batch->fee > 0 ? (float) $batch->fee : (float) $lockedCourse->price;
                $amount = $sale ? $sale->discountedPrice($base) : $base;
                if ($amount <= 0) {
                    Enrollment::firstOrCreate(
                        ['user_id' => $student->id, 'course_id' => $lockedCourse->id],
                        ['batch_id' => $batchId, 'flash_sale_id' => $sale?->id, 'status' => 'active']
                    );
                    return ['state' => 'enrolled', 'amount' => 0];
                }

                if (! $method) {
                    return ['state' => 'payment_method_required', 'amount' => round($amount, 2)];
                }

                $payment = Payment::create([
                    'user_id' => $student->id,
                    'course_id' => $lockedCourse->id,
                    'flash_sale_id' => $sale?->id,
                    'batch_id' => $batchId,
                    'method' => $method,
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => 'BDT',
                    'sender_account' => $validated['sender_account'] ?? null,
                    'transaction_reference' => $validated['transaction_reference'],
                    'proof_path' => $proofPath,
                    'status' => 'pending',
                ]);

                return ['state' => 'payment_submitted', 'payment_id' => $payment->id, 'amount' => round($amount, 2)];
            });
        } catch (Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }

        if (! in_array($result['state'], ['payment_submitted'], true) && $proofPath) {
            Storage::disk('local')->delete($proofPath);
        }

        $status = match ($result['state']) {
            'enrolled', 'payment_submitted' => 201,
            'already_enrolled', 'payment_pending' => 409,
            default => 409,
        };

        return response()->json([
            'status' => $result['state'],
            'message' => match ($result['state']) {
                'enrolled' => 'You are enrolled in this course.',
                'payment_submitted' => 'Payment submitted and awaiting verification.',
                'already_enrolled' => 'You are already enrolled in this course.',
                'payment_pending' => 'A payment is already awaiting verification.',
                'batch_unavailable' => 'The selected batch is full or no longer available.',
                'batch_required' => 'Select an open batch before checkout.',
                'sale_unavailable' => 'The Flash Sale has ended or reached its purchase limit.',
                default => 'Choose a payment method to continue checkout.',
            },
            'payment_id' => $result['payment_id'] ?? null,
            'amount' => $result['amount'] ?? null,
            'currency' => 'BDT',
        ], $status);
    }

    public function bkash(Request $request, Course $course, BkashCheckoutService $bkash): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless($course->status === 'published', 404);
        abort_unless((bool) (Setting::paymentMethods()['bkash']['enabled'] ?? false), 503, 'bKash is not enabled for checkout.');
        abort_unless($this->bkashConfigured(), 503, 'bKash checkout is not configured.');

        $hasOpenBatches = $course->batches()->where('status', 'open')->exists();
        $validated = $request->validate([
            'batch_id' => [
                $hasOpenBatches ? 'required' : 'nullable',
                'integer',
                Rule::exists('batches', 'id')->where(fn ($query) => $query
                    ->where('course_id', $course->id)->where('status', 'open')),
            ],
        ]);
        $batchId = $validated['batch_id'] ?? null;

        $payment = DB::transaction(function () use ($course, $student, $batchId): Payment {
            $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
            abort_unless($lockedCourse->status === 'published', 404);
            abort_if(Enrollment::query()->where('course_id', $lockedCourse->id)
                ->where('user_id', $student->id)->whereIn('status', ['active', 'completed'])->exists(), 409,
                'You are already enrolled in this course.');
            abort_if(Payment::query()->where('course_id', $lockedCourse->id)
                ->where('user_id', $student->id)->where('status', 'pending')->exists(), 409,
                'A payment is already awaiting verification.');

            $batch = null;
            if ($batchId) {
                $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
                abort_unless($batch->course_id === $lockedCourse->id && $batch->status === 'open'
                    && $this->hasAvailableSeat($batch), 409, 'The selected batch is full or unavailable.');
            } else {
                abort_if($lockedCourse->batches()->where('status', 'open')->exists(), 422,
                    'Select an open batch before checkout.');
            }

            $sale = FlashSale::activeForCourse($lockedCourse->id, $student->id);
            if ($sale) {
                $sale = FlashSale::query()->lockForUpdate()->find($sale->id);
                abort_unless($sale && $sale->targetsCourse($lockedCourse->id) && $sale->isAvailableFor($student->id),
                    409, 'The Flash Sale is no longer available.');
            }
            $base = $batch && (float) $batch->fee > 0 ? (float) $batch->fee : (float) $lockedCourse->price;
            $amount = $sale ? $sale->discountedPrice($base) : $base;
            abort_if($amount <= 0, 422, 'Free courses do not require bKash checkout.');

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
            $created = $bkash->createPayment($token, number_format((float) $payment->amount, 2, '.', ''),
                $payment->transaction_reference, route('payments.bkash.callback'), (string) $student->id);
            $paymentId = $created['paymentID'] ?? null;
            $checkoutUrl = $created['bkashURL'] ?? null;
            $host = is_string($checkoutUrl) ? parse_url($checkoutUrl, PHP_URL_HOST) : null;
            $allowedHosts = ['tokenized.sandbox.bka.sh', 'tokenized.pay.bka.sh', 'checkout.sandbox.bka.sh', 'checkout.pay.bka.sh'];
            abort_unless(($created['statusCode'] ?? null) === '0000' && is_string($paymentId) && $paymentId !== ''
                && is_string($checkoutUrl) && parse_url($checkoutUrl, PHP_URL_SCHEME) === 'https'
                && in_array($host, $allowedHosts, true), 502, 'bKash returned an invalid checkout response.');
            $payment->update(['gateway_payment_id' => $paymentId]);

            return response()->json([
                'status' => 'redirect_required',
                'payment_id' => $payment->id,
                'checkout_url' => $checkoutUrl,
                'amount' => (float) $payment->amount,
                'currency' => 'BDT',
            ], 201);
        } catch (Throwable $exception) {
            $payment->update(['status' => 'rejected',
                'admin_note' => 'bKash checkout could not be initialized. The student may retry.']);
            throw $exception;
        }
    }

    private function hasAvailableSeat(Batch $batch): bool
    {
        $enrolled = Enrollment::query()->where('batch_id', $batch->id)
            ->whereIn('status', ['active', 'completed'])->count();
        $pending = Payment::query()->where('batch_id', $batch->id)->where('status', 'pending')->count();

        return $enrolled + $pending < $batch->capacity;
    }

    private function bkashConfigured(): bool
    {
        return (bool) config('services.bkash.enabled')
            && filled(config('services.bkash.base_url'))
            && filled(config('services.bkash.username'))
            && filled(config('services.bkash.password'))
            && filled(config('services.bkash.app_key'))
            && filled(config('services.bkash.app_secret'));
    }
}
