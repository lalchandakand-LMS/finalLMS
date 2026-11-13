<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCombo;
use App\Models\PaymentComboCourse;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\FlashSale;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function submit(Course $course, Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isStudent()) {
            return back()->with('error', 'Only students can enroll in courses.');
        }

        abort_unless($course->status === 'published', 404);

        if ($course->enrollments()->where('user_id', $user->id)->exists()) {
            return back()->with('info', 'You are already enrolled in this course.');
        }

        $hasOpenBatches = $course->batches()->where('status', 'open')->exists();
        $validatedBatch = $request->validate([
            'batch_id' => [$hasOpenBatches ? 'required' : 'nullable', 'integer', Rule::exists('batches', 'id')->where(fn ($queryBuilder) => $queryBuilder->where('course_id', $course->id)->where('status', 'open'))],
        ]);
        $batchId = $validatedBatch['batch_id'] ?? null;
        $batchFee = $batchId ? (float) Batch::query()->whereKey($batchId)->value('fee') : 0;
        $baseAmount = $batchFee > 0 ? $batchFee : (float) $course->price;
        $flashSale = FlashSale::activeForCourse($course->id, $user->id);
        $amount = $flashSale ? $flashSale->discountedPrice($baseAmount) : $baseAmount;

        if ($amount <= 0) {
            $result = DB::transaction(function () use ($batchId, $course, $user): string {
                $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
                if ($lockedCourse->status !== 'published') {
                    return 'unavailable';
                }
                if (Enrollment::query()->where('course_id', $lockedCourse->id)->where('user_id', $user->id)->exists()) {
                    return 'already_enrolled';
                }

                if ($batchId) {
                    $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
                    if ($batch->course_id !== $lockedCourse->id || $batch->status !== 'open' || ! $this->hasAvailableSeat($batch)) {
                        return 'batch_full';
                    }
                } else {
                    $batch = null;
                }

                $sale = FlashSale::activeForCourse($lockedCourse->id, $user->id);
                if ($sale) {
                    $sale = FlashSale::query()->lockForUpdate()->find($sale->id);
                    if (! $sale || ! $sale->courses()->whereKey($lockedCourse->id)->exists() || ! $sale->isAvailableFor($user->id)) {
                        return 'sale_unavailable';
                    }
                }

                $base = $batch && (float) $batch->fee > 0 ? (float) $batch->fee : (float) $lockedCourse->price;
                $payable = $sale ? $sale->discountedPrice($base) : $base;
                if ($payable > 0) {
                    return 'price_changed';
                }

                Enrollment::firstOrCreate(
                    ['user_id' => $user->id, 'course_id' => $lockedCourse->id],
                    ['batch_id' => $batchId, 'flash_sale_id' => $sale?->id, 'status' => 'active']
                );

                return 'enrolled';
            });

            return match ($result) {
                'enrolled' => back()->with('success', 'You have enrolled in the course successfully.'),
                'already_enrolled' => back()->with('info', 'You are already enrolled in this course.'),
                'batch_full' => back()->with('error', 'The selected batch is full or no longer available.'),
                'sale_unavailable' => back()->with('error', 'This Flash Sale has ended or reached its purchase limit. Refresh the page and try again.'),
                'price_changed' => back()->with('warning', 'The course price changed. Refresh the page and review the current checkout price.'),
                default => back()->with('error', 'This course is no longer available.'),
            };
        }

        if ($course->payments()->where('user_id', $user->id)->where('status', 'pending')->exists()) {
            return back()->with('info', 'Your payment is already waiting for review.');
        }

        $enabledMethods = collect(Setting::paymentMethods())
            ->filter(fn (array $method) => (bool) ($method['enabled'] ?? false))
            ->keys()
            ->all();

        if ($enabledMethods === []) {
            return back()->with('error', 'There are no payment methods available right now. Please contact the academy.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in($enabledMethods)],
            'sender_account' => ['nullable', 'string', 'max:50'],
            'transaction_reference' => [
                'required',
                'string',
                'max:120',
                Rule::unique('payments', 'transaction_reference')
                    ->where(fn ($queryBuilder) => $queryBuilder->where('method', $request->input('payment_method'))),
            ],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $proof = $request->file('proof');
        $result = DB::transaction(function () use ($batchId, $course, $proof, $user, $validated): string {
            $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
            if ($lockedCourse->status !== 'published') {
                return 'unavailable';
            }
            if (Enrollment::query()->where('course_id', $lockedCourse->id)->where('user_id', $user->id)->exists()) {
                return 'already_enrolled';
            }
            if (Payment::query()->where('course_id', $lockedCourse->id)->where('user_id', $user->id)->where('status', 'pending')->exists()) {
                return 'payment_pending';
            }

            if ($batchId) {
                $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
                if ($batch->course_id !== $lockedCourse->id || $batch->status !== 'open' || ! $this->hasAvailableSeat($batch)) {
                    return 'batch_full';
                }
            } else {
                $batch = null;
            }

            $sale = FlashSale::activeForCourse($lockedCourse->id, $user->id);
            if ($sale) {
                $sale = FlashSale::query()->lockForUpdate()->find($sale->id);
                if (! $sale || ! $sale->courses()->whereKey($lockedCourse->id)->exists() || ! $sale->isAvailableFor($user->id)) {
                    return 'sale_unavailable';
                }
            }

            $base = $batch && (float) $batch->fee > 0 ? (float) $batch->fee : (float) $lockedCourse->price;
            $payable = $sale ? $sale->discountedPrice($base) : $base;
            if ($payable <= 0) {
                Enrollment::firstOrCreate(
                    ['user_id' => $user->id, 'course_id' => $lockedCourse->id],
                    ['batch_id' => $batchId, 'flash_sale_id' => $sale?->id, 'status' => 'active']
                );
                return 'enrolled_free';
            }

            $proofPath = $proof?->store('payment-proofs', 'local');
            Payment::create([
                'user_id' => $user->id,
                'course_id' => $lockedCourse->id,
                'flash_sale_id' => $sale?->id,
                'batch_id' => $batchId,
                'method' => $validated['payment_method'],
                'amount' => $payable,
                'currency' => 'BDT',
                'sender_account' => $validated['sender_account'] ?? null,
                'transaction_reference' => $validated['transaction_reference'],
                'proof_path' => $proofPath,
                'status' => 'pending',
            ]);

            return 'submitted';
        });

        return match ($result) {
            'submitted' => redirect()->route('courses.show', $course)->with('success', 'Payment submitted. Course access will be available after the admin verifies it.'),
            'enrolled_free' => redirect()->route('courses.show', $course)->with('success', 'You have enrolled in the course successfully.'),
            'already_enrolled' => back()->with('info', 'You are already enrolled in this course.'),
            'payment_pending' => back()->with('info', 'Your payment is already waiting for review.'),
            'batch_full' => back()->with('error', 'The selected batch is full or no longer available.'),
            'sale_unavailable' => back()->with('error', 'This Flash Sale has ended or reached its purchase limit. Refresh the page and try again.'),
            default => back()->with('error', 'The course is no longer available. Refresh the page and try again.'),
        };
    }

    public function submitCombo(CourseCombo $courseCombo, Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isStudent()) {
            return back()->with('error', 'Only students can purchase course combos.');
        }

        $courseCombo->load('courses');
        abort_unless($courseCombo->status === 'published' && $courseCombo->courses->count() >= 2, 404);
        if ($courseCombo->courses->contains(fn (Course $course) => $course->status !== 'published'
            || $course->batches()->where('status', 'open')->exists())) {
            return back()->with('error', 'This combo is unavailable because an included course has an open batch or is unpublished.');
        }

        $enabledMethods = collect(Setting::paymentMethods())
            ->filter(fn (array $method) => (bool) ($method['enabled'] ?? false))
            ->keys()
            ->all();

        $validated = [];
        if ((float) $courseCombo->price > 0) {
            if ($enabledMethods === []) {
                return back()->with('error', 'There are no payment methods available right now. Please contact the academy.');
            }

            $validated = $request->validate([
                'payment_method' => ['required', Rule::in($enabledMethods)],
                'sender_account' => ['nullable', 'string', 'max:50'],
                'transaction_reference' => [
                    'required',
                    'string',
                    'max:120',
                    Rule::unique('payments', 'transaction_reference')
                        ->where(fn ($queryBuilder) => $queryBuilder->where('method', $request->input('payment_method'))),
                ],
                'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);
        }

        $proof = $request->file('proof');
        $result = DB::transaction(function () use ($courseCombo, $proof, $user, $validated): string {
            $lockedCombo = CourseCombo::query()->lockForUpdate()->with('courses')->findOrFail($courseCombo->id);
            $courses = $lockedCombo->courses;
            if ($lockedCombo->status !== 'published' || $courses->count() < 2
                || $courses->contains(fn (Course $course) => $course->status !== 'published'
                    || $course->batches()->where('status', 'open')->exists())) {
                return 'unavailable';
            }

            $courseIds = $courses->pluck('id')->all();
            $alreadyEnrolled = Enrollment::query()
                ->where('user_id', $user->id)
                ->whereIn('course_id', $courseIds)
                ->whereIn('status', ['active', 'completed'])
                ->count();

            if ($alreadyEnrolled === count($courseIds)) {
                return 'already_enrolled';
            }

            if ((float) $lockedCombo->price <= 0) {
                foreach ($courses as $course) {
                    Enrollment::firstOrCreate(
                        ['user_id' => $user->id, 'course_id' => $course->id],
                        ['status' => 'active']
                    );
                }

                return 'enrolled_free';
            }

            if (Payment::query()->where('user_id', $user->id)
                ->where('course_combo_id', $lockedCombo->id)
                ->where('status', 'pending')->exists()) {
                return 'payment_pending';
            }

            $proofPath = $proof?->store('payment-proofs', 'local');
            $payment = Payment::create([
                'user_id' => $user->id,
                'course_id' => $courses->first()->id,
                'course_combo_id' => $lockedCombo->id,
                'method' => $validated['payment_method'],
                'amount' => $lockedCombo->price,
                'currency' => 'BDT',
                'sender_account' => $validated['sender_account'] ?? null,
                'transaction_reference' => $validated['transaction_reference'],
                'proof_path' => $proofPath,
                'status' => 'pending',
            ]);

            foreach ($courses->values() as $position => $course) {
                PaymentComboCourse::create([
                    'payment_id' => $payment->id,
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'position' => $position + 1,
                ]);
            }

            return 'submitted';
        });

        return match ($result) {
            'submitted' => back()->with('success', 'Combo payment submitted. Access to all included courses will be granted after academy verification.'),
            'enrolled_free' => redirect()->route('course-combos.show', $courseCombo)->with('success', 'You have enrolled in all combo courses successfully.'),
            'already_enrolled' => back()->with('info', 'You are already enrolled in every course in this combo.'),
            'payment_pending' => back()->with('info', 'Your combo payment is already waiting for review.'),
            default => back()->with('error', 'This combo is no longer available. Refresh the page and try again.'),
        };
    }

    public function index(): View
    {
        $payments = Payment::with(['user', 'course', 'courseCombo', 'comboCourses', 'reviewer'])
            ->latest()
            ->paginate(20);

        return view('admin.payments.index', compact('payments'));
    }

    public function approve(Payment $payment): RedirectResponse
    {
        if (filled($payment->gateway_provider)) {
            return back()->with('error', 'Gateway payments can only be approved after server-side verification.');
        }

        $result = DB::transaction(function () use ($payment): string {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'pending') {
                return 'reviewed';
            }

            if ($payment->batch_id) {
                $batch = Batch::query()->lockForUpdate()->findOrFail($payment->batch_id);
                if ($batch->status !== 'open' || ! $this->hasAvailableSeat($batch, false)) {
                    return 'full';
                }
            }

            if ($payment->course_combo_id) {
                $comboItems = PaymentComboCourse::query()
                    ->where('payment_id', $payment->id)
                    ->orderBy('position')
                    ->get();
                $courseIds = $comboItems->pluck('course_id')->filter()->unique()->values();

                if ($comboItems->count() < 2 || $courseIds->count() !== $comboItems->count()) {
                    return 'combo_unavailable';
                }

                $courses = Course::query()->whereIn('id', $courseIds)->get()->keyBy('id');
                if ($courses->count() !== $comboItems->count()
                    || $courses->contains(fn (Course $course) => $course->status !== 'published')) {
                    return 'combo_unavailable';
                }

                foreach ($comboItems as $item) {
                    Enrollment::firstOrCreate(
                        ['user_id' => $payment->user_id, 'course_id' => $item->course_id],
                        ['status' => 'active']
                    );
                }
            } else {
                Enrollment::firstOrCreate(
                    ['user_id' => $payment->user_id, 'course_id' => $payment->course_id],
                    ['batch_id' => $payment->batch_id, 'status' => 'active']
                );
            }

            $payment->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            return 'approved';
        });

        if ($result === 'full') {
            return back()->with('error', 'The batch is full. Payment remains pending and requires manual resolution.');
        }

        if ($result === 'reviewed') {
            return back()->with('info', 'This payment has already been reviewed.');
        }

        if ($result === 'combo_unavailable') {
            return back()->with('error', 'The purchased combo no longer contains all its original published courses. Payment remains pending for manual resolution.');
        }

        return back()->with('success', 'Payment approved and course access granted.');
    }

    public function reject(Payment $payment, Request $request): RedirectResponse
    {
        if (filled($payment->gateway_provider)) {
            return back()->with('error', 'Gateway payments must be reconciled through the payment provider.');
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($payment->status !== 'pending') {
            return back()->with('info', 'This payment has already been reviewed.');
        }

        $payment->update([
            'status' => 'rejected',
            'admin_note' => $validated['admin_note'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Payment rejected. The student can submit a corrected payment.');
    }

    public function proof(Payment $payment)
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download($payment->proof_path);
    }

    private function hasAvailableSeat(Batch $batch, bool $includePendingPayments = true): bool
    {
        $enrollments = Enrollment::query()
            ->where('batch_id', $batch->id)
            ->whereIn('status', ['active', 'completed'])
            ->count();
        $pendingPayments = $includePendingPayments
            ? Payment::query()->where('batch_id', $batch->id)->where('status', 'pending')->count()
            : 0;

        return $enrollments + $pendingPayments < $batch->capacity;
    }
}