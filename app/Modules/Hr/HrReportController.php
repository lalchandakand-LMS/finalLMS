<?php

declare(strict_types=1);

namespace App\Modules\Hr;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

final class HrReportController extends Controller
{
    public function payrollIndex(Request $request): View
    {
        $actor = $request->user();
        $this->assertActorBranch($actor);
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:pending,paid'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $month = $filters['month'] ?? now()->format('Y-m');
        $query = StaffPayroll::query()->with('staff')->where('payroll_month', $month);
        $this->scopeBranch($query, $actor, $filters['branch_id'] ?? null);
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('modules.hr.payroll-report', [
            'payrolls' => (clone $query)->orderBy('id')->paginate(50)->withQueryString(),
            'month' => $month,
            'status' => $filters['status'] ?? '',
            'totals' => [
                'records' => (clone $query)->count(),
                'gross' => (float) (clone $query)->sum('gross_salary'),
                'bonus' => (float) (clone $query)->sum('bonus'),
                'deductions' => (float) (clone $query)->sum('deductions'),
                'net' => (float) (clone $query)->sum('net_paid'),
                'paid' => (clone $query)->where('status', 'paid')->count(),
                'pending' => (clone $query)->where('status', 'pending')->count(),
            ],
            'branches' => $actor->isAdmin() ? Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'selectedBranch' => $actor->isAdmin() ? ($filters['branch_id'] ?? '') : '',
        ]);
    }

    public function exportPayroll(Request $request): StreamedResponse
    {
        $actor = $request->user();
        $this->assertActorBranch($actor);
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:pending,paid'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $month = $filters['month'] ?? now()->format('Y-m');
        $query = StaffPayroll::query()->with('staff')->where('payroll_month', $month);
        $this->scopeBranch($query, $actor, $filters['branch_id'] ?? null);
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Payroll ID', 'Employee Code', 'Employee Name', 'Department', 'Designation', 'Branch ID', 'Payroll Month', 'Compensation Basis', 'Basic Salary', 'Allowances', 'Overtime Hours', 'Overtime Rate', 'Overtime Amount', 'Gross Salary', 'Bonus', 'Deductions', 'Attendance Deduction', 'Net Pay', 'Status', 'Paid At', 'Payment Reference']);
            $query->chunkById(500, function ($rows) use ($output): void {
                foreach ($rows as $payroll) {
                    $staff = $payroll->staff;
                    fputcsv($output, [
                        $payroll->id,
                        $this->csvSafe($staff?->employee_code),
                        $this->csvSafe($staff?->full_name),
                        $this->csvSafe($staff?->department),
                        $this->csvSafe($staff?->designation),
                        $payroll->branch_id,
                        $payroll->payroll_month,
                        $payroll->compensation_basis_snapshot,
                        $payroll->basic_salary,
                        $payroll->allowances,
                        $payroll->overtime_hours,
                        $payroll->overtime_rate,
                        $payroll->overtime_amount,
                        $payroll->gross_salary,
                        $payroll->bonus,
                        $payroll->deductions,
                        $payroll->attendance_deduction,
                        $payroll->net_paid,
                        $payroll->status,
                        $payroll->paid_at?->toDateTimeString(),
                        $this->csvSafe($payroll->payment_reference),
                    ]);
                }
            });
            fclose($output);
        }, 'hr-payroll-'.$month.'-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function leaveCalendar(Request $request): View
    {
        $actor = $request->user();
        $this->assertActorBranch($actor);
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $month = $filters['month'] ?? now()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');
        $end = $start->endOfMonth();
        $query = StaffLeaveRequest::query()
            ->with(['staff', 'reviewer'])
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString());
        $this->scopeBranch($query, $actor, $filters['branch_id'] ?? null);
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $leaves = $query->orderBy('starts_on')->orderBy('staff_profile_id')->limit(2000)->get();
        $calendarDays = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $calendarDays[] = [
                'date' => $day,
                'leaves' => $leaves->filter(fn (StaffLeaveRequest $leave): bool =>
                    $leave->starts_on->lte($day) && $leave->ends_on->gte($day)
                )->values(),
            ];
        }

        return view('modules.hr.leave-calendar', [
            'month' => $month,
            'status' => $filters['status'] ?? '',
            'calendarDays' => $calendarDays,
            'leaves' => $leaves,
            'totals' => [
                'requests' => $leaves->count(),
                'pending' => $leaves->where('status', 'pending')->count(),
                'approved' => $leaves->where('status', 'approved')->count(),
                'rejected' => $leaves->where('status', 'rejected')->count(),
            ],
            'branches' => $actor->isAdmin() ? Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'selectedBranch' => $actor->isAdmin() ? ($filters['branch_id'] ?? '') : '',
        ]);
    }

    public function exportLeaveReport(Request $request): StreamedResponse
    {
        $actor = $request->user();
        $this->assertActorBranch($actor);
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $month = $filters['month'] ?? now()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');
        $end = $start->endOfMonth();
        $query = StaffLeaveRequest::query()->with('staff')
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString());
        $this->scopeBranch($query, $actor, $filters['branch_id'] ?? null);
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Leave ID', 'Employee Code', 'Employee Name', 'Department', 'Branch ID', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Status', 'Reason', 'Review Note', 'Reviewed At']);
            $query->orderBy('id')->chunkById(500, function ($rows) use ($output): void {
                foreach ($rows as $leave) {
                    fputcsv($output, [
                        $leave->id,
                        $this->csvSafe($leave->staff?->employee_code),
                        $this->csvSafe($leave->staff?->full_name),
                        $this->csvSafe($leave->staff?->department),
                        $leave->branch_id,
                        $leave->leave_type,
                        $leave->starts_on?->toDateString(),
                        $leave->ends_on?->toDateString(),
                        $leave->starts_on && $leave->ends_on ? $leave->starts_on->diffInDays($leave->ends_on) + 1 : 0,
                        $leave->status,
                        $this->csvSafe($leave->reason),
                        $this->csvSafe($leave->review_note),
                        $leave->reviewed_at?->toDateTimeString(),
                    ]);
                }
            });
            fclose($output);
        }, 'hr-leave-report-'.$month.'-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function assertActorBranch(User $actor): void
    {
        abort_unless($actor->isAdmin() || $actor->isBranchManager(), 403);
        if ($actor->isBranchManager()) {
            abort_unless(
                $actor->branch_id !== null && Branch::query()->whereKey($actor->branch_id)->where('is_active', true)->exists(),
                403,
                'Branch manager must be assigned to an active branch.'
            );
        }
    }

    private function scopeBranch($query, User $actor, mixed $requestedBranchId = null): void
    {
        if ($actor->isBranchManager()) {
            $query->where('branch_id', $actor->branch_id);
        } elseif ($requestedBranchId !== null && $requestedBranchId !== '') {
            $query->where('branch_id', (int) $requestedBranchId);
        }
    }

    private function csvSafe(?string $value): string
    {
        $value = (string) $value;
        if ($value !== '' && preg_match('/^[=+@\-\t\r]/u', $value) === 1) {
            return "'".$value;
        }
        return $value;
    }
}
