<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
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
            abort_unless($actor->branch_id && DB::table('branches')->where('id', $actor->branch_id)->where('is_active', true)->exists(), 403);
            abort_unless((int) $batch->branch_id === (int) $actor->branch_id, 404);
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
            abort_if($periodEnd->isFuture(), 422, 'Class-fee billing can only be generated after the selected month ends, so all sessions are included.');
            $enrollments = $lockedBatch->enrollments()
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

                $attendedClasses = (int) DB::table('live_class_attendances as class_attendance')
                    ->join('live_classes', 'live_classes.id', '=', 'class_attendance.live_class_id')
                    ->where('live_classes.batch_id', $lockedBatch->id)
                    ->where('live_classes.status', 'completed')
                    ->where('class_attendance.student_id', $studentId)
                    ->whereBetween('live_classes.starts_at', [$periodStart->startOfDay()->toDateTimeString(), $periodEnd->endOfDay()->toDateTimeString()])
                    ->whereIn('class_attendance.status', ['present', 'late'])
                    ->count();

                if ($attendedClasses < 1) {
                    $noAttendance++;
                    continue;
                }

                $amount = round($attendedClasses * $rate, 2);
                abort_if($amount > 99999999.99, 422, 'The calculated class fee exceeds the maximum supported invoice amount.');
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

        try {
            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'finance.attendance_fee_invoices_generated',
                'description' => 'Generated '.$result['created'].' attendance-based class fee invoice(s) for batch #'.$batch->id.' in '.$data['billing_period'].'.',
                'method' => $request->method(),
                'route' => optional($request->route())->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', sprintf(
            'Class-fee billing complete: %d invoice(s) created, %d already billed, %d student(s) had no payable attendance.',
            $result['created'], $result['alreadyBilled'], $result['noAttendance']
        ));
    }
}
