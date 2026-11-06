<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Payment;
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
        $amount = $batchFee > 0 ? $batchFee : (float) $course->price;

        if ($amount <= 0) {
            $enrolled = DB::transaction(function () use ($batchId, $course, $user): bool {
                if ($batchId) {
                    $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
                    if ($batch->status !== 'open' || ! $this->hasAvailableSeat($batch)) {
                        return false;
                    }
                }

                Enrollment::firstOrCreate(
                    ['user_id' => $user->id, 'course_id' => $course->id],
                    ['batch_id' => $batchId, 'status' => 'active']
                );

                return true;
            });

            if (! $enrolled) {
                return back()->with('error', 'This batch has reached its enrollment capacity.');
            }

            return back()->with('success', 'You have enrolled in the course successfully.');
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
        $submitted = DB::transaction(function () use ($amount, $batchId, $course, $proof, $user, $validated): bool {
            if ($batchId) {
                $batch = Batch::query()->lockForUpdate()->findOrFail($batchId);
                if ($batch->status !== 'open' || ! $this->hasAvailableSeat($batch)) {
                    return false;
                }
            }

            $proofPath = $proof?->store('payment-proofs', 'local');

            Payment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'batch_id' => $batchId,
                'method' => $validated['payment_method'],
                'amount' => $amount,
                'currency' => 'BDT',
                'sender_account' => $validated['sender_account'] ?? null,
                'transaction_reference' => $validated['transaction_reference'],
                'proof_path' => $proofPath,
                'status' => 'pending',
            ]);

            return true;
        });

        if (! $submitted) {
            return back()->with('error', 'This batch has reached its enrollment capacity.');
        }

        return redirect()->route('courses.show', $course)->with('success', 'Payment submitted. Course access will be available after the admin verifies it.');
    }

    public function index(): View
    {
        $payments = Payment::with(['user', 'course', 'reviewer'])
            ->latest()
            ->paginate(20);

        return view('admin.payments.index', compact('payments'));
    }

    public function approve(Payment $payment): RedirectResponse
    {
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

            $payment->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            Enrollment::firstOrCreate(
                ['user_id' => $payment->user_id, 'course_id' => $payment->course_id],
                ['batch_id' => $payment->batch_id, 'status' => 'active']
            );

            return 'approved';
        });

        if ($result === 'full') {
            return back()->with('error', 'The batch is full. Payment remains pending and requires manual resolution.');
        }

        if ($result === 'reviewed') {
            return back()->with('info', 'This payment has already been reviewed.');
        }

        return back()->with('success', 'Payment approved and course access granted.');
    }

    public function reject(Payment $payment, Request $request): RedirectResponse
    {
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