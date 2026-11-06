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

        $validatedBatch = $request->validate([
            'batch_id' => ['nullable', 'integer', Rule::exists('batches', 'id')->where(fn ($queryBuilder) => $queryBuilder->where('course_id', $course->id)->where('status', 'open'))],
        ]);
        $batch = isset($validatedBatch['batch_id'])
            ? Batch::withCount('enrollments')->findOrFail($validatedBatch['batch_id'])
            : null;

        $pendingBatchPayments = $batch
            ? Payment::where('batch_id', $batch->id)->where('status', 'pending')->count()
            : 0;

        if ($batch && $batch->enrollments_count + $pendingBatchPayments >= $batch->capacity) {
            return back()->with('error', 'This batch has reached its enrollment capacity.');
        }

        if ((float) $course->price <= 0) {
            Enrollment::firstOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                ['batch_id' => $batch?->id, 'status' => 'active']
            );

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

        $proofPath = $request->file('proof')?->store('payment-proofs', 'local');

        Payment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'batch_id' => $batch?->id,
            'method' => $validated['payment_method'],
            'amount' => $course->price,
            'currency' => 'BDT',
            'sender_account' => $validated['sender_account'] ?? null,
            'transaction_reference' => $validated['transaction_reference'],
            'proof_path' => $proofPath,
            'status' => 'pending',
        ]);

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
        $capacityReached = false;
        DB::transaction(function () use ($payment, &$capacityReached): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'pending') {
                return;
            }

            if ($payment->batch_id) {
                $batch = Batch::query()->lockForUpdate()->findOrFail($payment->batch_id);
                if ($batch->enrollments()->count() >= $batch->capacity) {
                    $capacityReached = true;

                    return;
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
        });

        if ($capacityReached) {
            return back()->with('error', 'The batch has reached capacity. No enrollment was created.');
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
}