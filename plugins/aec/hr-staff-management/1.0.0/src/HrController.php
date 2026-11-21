<?php

declare(strict_types=1);

namespace Aloms\HrStaffManagement;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class HrController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $staffQuery = $this->scoped(StaffProfile::query(), $actor);
        $payrollQuery = $this->scoped(StaffPayroll::query()->with('staff'), $actor);

        return view('hr-staff-management::index', [
            'staff' => $staffQuery->orderBy('full_name')->paginate(25, ['*'], 'staff_page')->withQueryString(),
            'payrolls' => $payrollQuery->latest()->limit(60)->get(),
        ]);
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/D', 'unique:staff_profiles,employee_code'],
            'full_name' => ['required', 'string', 'max:160'],
            'designation' => ['required', 'string', 'max:120'],
            'department' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'status' => ['required', 'in:active,on_leave,inactive'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        if ($actor->isBranchManager()) {
            $data['branch_id'] = $actor->branch_id;
        } elseif (! empty($data['branch_id'])) {
            abort_unless(DB::table('branches')->where('id', $data['branch_id'])->where('is_active', true)->exists(), 422, 'Select an active branch.');
        }

        $member = StaffProfile::query()->create($data + ['joined_at' => now()->toDateString()]);
        $this->audit($request, 'hr.staff_created', 'Created staff profile '.$member->employee_code);

        return back()->with('success', 'Staff profile created.');
    }

    public function recordPayroll(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'payroll_month' => ['required', 'date_format:Y-m'],
            'bonus' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'deductions' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $payroll = DB::transaction(function () use ($actor, $data): StaffPayroll {
            $member = StaffProfile::query()->lockForUpdate()->findOrFail($data['staff_profile_id']);
            $this->assertRecordBranch($actor, $member->branch_id);
            abort_unless($member->status === 'active', 422, 'Payroll can only be prepared for active staff.');
            $bonus = round((float) ($data['bonus'] ?? 0), 2);
            $deductions = round((float) ($data['deductions'] ?? 0), 2);
            $gross = round((float) $member->base_salary, 2);
            abort_if($deductions > $gross + $bonus, 422, 'Deductions cannot exceed gross salary plus bonus.');
            abort_if(StaffPayroll::query()->where('staff_profile_id', $member->id)->where('payroll_month', $data['payroll_month'])->exists(), 409, 'Payroll already exists for this staff member and month.');

            return StaffPayroll::query()->create([
                'staff_profile_id' => $member->id,
                'branch_id' => $member->branch_id,
                'payroll_month' => $data['payroll_month'],
                'gross_salary' => $gross,
                'bonus' => $bonus,
                'deductions' => $deductions,
                'net_paid' => round($gross + $bonus - $deductions, 2),
                'status' => 'pending',
            ]);
        }, 3);

        $this->audit($request, 'hr.payroll_recorded', 'Prepared payroll #'.$payroll->id.' for '.$payroll->payroll_month);

        return back()->with('success', 'Payroll recorded as pending; payment must be confirmed separately.');
    }

    public function markPayrollPaid(Request $request, int $payroll): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin(), 403, 'Only a central administrator can confirm payroll disbursement.');
        $data = $request->validate(['payment_reference' => ['required', 'string', 'max:120']]);

        $record = DB::transaction(function () use ($payroll, $data): StaffPayroll {
            $locked = StaffPayroll::query()->lockForUpdate()->findOrFail($payroll);
            abort_unless($locked->status === 'pending', 409, 'Only pending payroll can be marked paid.');
            $locked->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => trim($data['payment_reference']),
            ])->save();

            return $locked->fresh();
        }, 3);
        $this->audit($request, 'hr.payroll_marked_paid', 'Confirmed payroll #'.$record->id.' with a disbursement reference.');

        return back()->with('success', 'Payroll marked as paid.');
    }

    private function scoped($query, User $actor)
    {
        if ($actor->isBranchManager()) {
            return $query->where('branch_id', $actor->branch_id);
        }

        return $query;
    }

    private function assertBranchAssigned(User $actor): void
    {
        if ($actor->isBranchManager()) {
            abort_unless($actor->branch_id !== null
                && DB::table('branches')->where('id', $actor->branch_id)->where('is_active', true)->exists(), 403,
                'Branch manager must be assigned to an active branch.');
        }
    }

    private function assertRecordBranch(User $actor, ?int $branchId): void
    {
        if ($actor->isBranchManager()) {
            abort_unless((int) $actor->branch_id === (int) $branchId, 404);
        }
    }

    private function audit(Request $request, string $event, string $description): void
    {
        try {
            ActivityLog::query()->create([
                'user_id' => $request->user()?->id,
                'event' => $event,
                'description' => mb_substr($description, 0, 255),
                'method' => $request->method(),
                'route' => optional($request->route())->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
