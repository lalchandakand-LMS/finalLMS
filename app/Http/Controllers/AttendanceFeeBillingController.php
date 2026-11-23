<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttendanceFeePeriod;
use App\Models\Batch;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AttendanceFeeBillingController extends Controller
{
    public function generate(Batch $batch, Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->isAdmin() || $actor->isBranchManager()), 403);
        if ($actor->isBranchManager()) {
            abort_unless($actor->hasAdminPermission('finance.invoices'), 403);
            abort_unless($actor->branch_id && (int) $batch->branch_id === (int) $actor->branch_id, 404);
        }

        $data = $request->validate([
            'billing_period' => ['required', 'date_format:Y-m'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $result = DB::transaction(function () use ($batch, $actor, $data): array {
            $lockedBatch = Batch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_unless($lockedBatch->fee_calculation_mode === 'per_class', 422, 'Enable per-class fee calculation for this batch first.');
            $rate = round((float) $lockedBatch->per_class_fee, 2);
            abort_if($rate <= 0, 422, 'Set a per-class fee greater than zero before generating invoices.');

            $periodStart = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $data['billing_period'].'-01');
            $periodEnd = $periodStart->endOfMonth();
            $enrollments = $lockedBatch->enrollments()
                ->whereIn('status', ['active', 'completed'])
                ->with('user')
                ->lockForUpdate()
                ->get();

            $created = 0;
            $alreadyBilled = 0;
            $noAttendance = 0;

            foreach ($enrollments as $enrollment) {
                $studentId = (int) $enrollment->user_id;
                if (AttendanceFeePeriod::query()
                    ->where('batch_id', $lockedBatch->id)
                    ->where('student_id', $studentId)
                    ->where('billing_period', $data['billing_period'])
                    ->exists()) {
                    $alreadyBilled++;
                    continue;
                }

                $attendedClasses = (int) DB::table('attendances')
                    ->where('batch_id', $lockedBatch->id)
                    ->where('student_id', $studentId)
                    ->whereBetween('attendance_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->whereIn('status', ['present', 'late'])
                    ->count();

                if ($attendedClasses < 1) {
                    $noAttendance++;
                    continue;
                }

                $amount = round($attendedClasses * $rate, 2);
                $invoice = Invoice::query()->create([
                    'user_id' => $studentId,
                    'course_id' => $lockedBatch->course_id,
                    'batch_id' => $lockedBatch->id,
                    'created_by' => $actor->id,
                    'invoice_number' => 'AEC-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'title' => $lockedBatch->title.' class fee — '.$data['billing_period'],
                    'description' => $attendedClasses.' attended classes × BDT '.number_format($rate, 2).' per class.',
                    'amount' => $amount,
                    'subtotal' => $amount,
                    'due_date' => $data['due_date'] ?? null,
                    'status' => 'unpaid',
                ]);

                AttendanceFeePeriod::query()->create([
                    'batch_id' => $lockedBatch->id,
                    'student_id' => $studentId,
                    'billing_period' => $data['billing_period'],
                    'attended_classes' => $attendedClasses,
                    'per_class_fee' => $rate,
                    'amount' => $amount,
                    'invoice_id' => $invoice->id,
                    'generated_by' => $actor->id,
                    'generated_at' => now(),
                ]);
                $created++;
            }

            return compact('created', 'alreadyBilled', 'noAttendance');
        }, 3);

        return back()->with('success', sprintf(
            'Class-fee billing complete: %d invoice(s) created, %d already billed, %d student(s) had no payable attendance.',
            $result['created'], $result['alreadyBilled'], $result['noAttendance']
        ));
    }
}
