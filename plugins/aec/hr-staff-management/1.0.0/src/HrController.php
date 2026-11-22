<?php

declare(strict_types=1);

namespace Aloms\HrStaffManagement;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class HrController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $staffQuery = $this->scoped(StaffProfile::query(), $actor);
        $payrollQuery = $this->scoped(StaffPayroll::query()->with('staff'), $actor);
        $attendanceQuery = $this->scoped(StaffAttendance::query()->with('staff'), $actor);
        $leaveQuery = $this->scoped(StaffLeaveRequest::query()->with('staff'), $actor);

        return view('hr-staff-management::index', [
            'staff' => $staffQuery->orderBy('full_name')->paginate(25, ['*'], 'staff_page')->withQueryString(),
            'payrolls' => $payrollQuery->latest()->limit(60)->get(),
            'attendances' => $attendanceQuery->whereDate('attendance_date', '>=', now()->subDays(30)->toDateString())->latest('attendance_date')->limit(100)->get(),
            'leaveRequests' => $leaveQuery->latest()->limit(100)->get(),
        ]);
    }

    public function teacherIndex(Request $request): View
    {
        $teacher = $request->user();
        abort_unless($teacher && $teacher->isTeacher(), 403);
        $profile = StaffProfile::query()->where('user_id', $teacher->id)->firstOrFail();
        $attendances = $profile->attendances()->latest('attendance_date')->limit(60)->get();
        $leaveRequests = $profile->leaveRequests()->latest()->limit(30)->get();

        return view('hr-staff-management::teacher', compact('profile', 'attendances', 'leaveRequests'));
    }

    public function teacherRequestLeave(Request $request): RedirectResponse
    {
        $teacher = $request->user();
        abort_unless($teacher && $teacher->isTeacher(), 403);
        $profile = StaffProfile::query()->where('user_id', $teacher->id)->firstOrFail();
        $data = $request->validate([
            'leave_type' => ['required', 'in:annual,sick,casual,unpaid,other'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $leave = DB::transaction(function () use ($profile, $data): StaffLeaveRequest {
            $lockedProfile = StaffProfile::query()->lockForUpdate()->findOrFail($profile->id);
            abort_unless($lockedProfile->status === 'active', 422, 'Only active teachers can request leave.');
            $overlap = StaffLeaveRequest::query()->where('staff_profile_id', $lockedProfile->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('starts_on', '<=', $data['ends_on'])
                ->whereDate('ends_on', '>=', $data['starts_on'])->exists();
            abort_if($overlap, 409, 'This leave period overlaps an existing pending or approved request.');
            return StaffLeaveRequest::query()->create($data + ['branch_id' => $lockedProfile->branch_id, 'status' => 'pending']);
        }, 3);
        $this->audit($request, 'hr.teacher_leave_requested', 'Teacher submitted leave request #'.$leave->id);
        return back()->with('success', 'Your leave request has been submitted.');
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
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:staff_profiles,user_id'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,temporary'],
            'contract_ends_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        if ($actor->isBranchManager()) {
            $data['branch_id'] = $actor->branch_id;
        } elseif (! empty($data['branch_id'])) {
            abort_unless(DB::table('branches')->where('id', $data['branch_id'])->where('is_active', true)->exists(), 422, 'Select an active branch.');
        }
        if (! empty($data['user_id'])) {
            $linkedUser = User::query()->findOrFail($data['user_id']);
            abort_unless(in_array($linkedUser->role, ['teacher', 'branch_manager', 'staff'], true), 422, 'Link only a teacher, branch manager or staff account.');
            if ($actor->isBranchManager()) {
                abort_unless($linkedUser->branch_id === null || (int) $linkedUser->branch_id === (int) $actor->branch_id, 422, 'You can only link an account assigned to your branch.');
            }
            if (! empty($data['branch_id'])) {
                abort_unless($linkedUser->branch_id === null || (int) $linkedUser->branch_id === (int) $data['branch_id'], 422, 'The linked account belongs to another branch.');
            }
        }

        $member = StaffProfile::query()->create($data + ['joined_at' => now()->toDateString()]);
        $this->audit($request, 'hr.staff_created', 'Created staff profile '.$member->employee_code);

        return back()->with('success', 'Staff profile created.');
    }

    public function recordAttendance(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave'])],
            'check_in_at' => ['nullable', 'date_format:H:i'],
            'check_out_at' => ['nullable', 'date_format:H:i', 'after:check_in_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $attendance = DB::transaction(function () use ($actor, $data, $request): StaffAttendance {
            $member = StaffProfile::query()->lockForUpdate()->findOrFail($data['staff_profile_id']);
            $this->assertRecordBranch($actor, $member->branch_id);
            abort_unless($member->status === 'active', 422, 'Attendance can only be recorded for active staff.');
            return StaffAttendance::query()->updateOrCreate(
                ['staff_profile_id' => $member->id, 'attendance_date' => $data['attendance_date']],
                ['branch_id' => $member->branch_id, 'status' => $data['status'], 'check_in_at' => $data['check_in_at'] ?? null,
                 'check_out_at' => $data['check_out_at'] ?? null, 'notes' => $data['notes'] ?? null, 'recorded_by' => $request->user()->id]
            );
        }, 3);
        $this->audit($request, 'hr.attendance_recorded', 'Recorded staff attendance #'.$attendance->id);
        return back()->with('success', 'Staff attendance saved.');
    }

    public function requestLeave(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'leave_type' => ['required', 'in:annual,sick,casual,unpaid,other'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $leave = DB::transaction(function () use ($actor, $data): StaffLeaveRequest {
            $member = StaffProfile::query()->lockForUpdate()->findOrFail($data['staff_profile_id']);
            $this->assertRecordBranch($actor, $member->branch_id);
            abort_unless($member->status === 'active', 422, 'Leave can only be requested for active staff.');
            $overlap = StaffLeaveRequest::query()
                ->where('staff_profile_id', $member->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('starts_on', '<=', $data['ends_on'])
                ->whereDate('ends_on', '>=', $data['starts_on'])
                ->exists();
            abort_if($overlap, 409, 'This leave period overlaps an existing pending or approved request.');
            return StaffLeaveRequest::query()->create($data + ['branch_id' => $member->branch_id, 'status' => 'pending']);
        }, 3);
        $this->audit($request, 'hr.leave_requested', 'Created leave request #'.$leave->id);
        return back()->with('success', 'Leave request submitted for approval.');
    }

    public function reviewLeave(Request $request, int $leave): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin(), 403, 'Only a central administrator can approve or reject leave.');
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'review_note' => ['nullable', 'string', 'max:1000']]);
        $record = DB::transaction(function () use ($leave, $data, $actor): StaffLeaveRequest {
            $locked = StaffLeaveRequest::query()->lockForUpdate()->findOrFail($leave);
            abort_unless($locked->status === 'pending', 409, 'Only pending leave requests can be reviewed.');
            $locked->forceFill(['status' => $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
            return $locked->fresh();
        }, 3);
        $this->audit($request, 'hr.leave_reviewed', 'Reviewed leave request #'.$record->id.' as '.$record->status);
        return back()->with('success', 'Leave request '.$record->status.'.');
    }

    public function recordPayroll(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'payroll_month' => ['required', 'date_format:Y-m'],
            'allowances' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:744'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'bonus' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'deductions' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);
        $payroll = DB::transaction(function () use ($actor, $data): StaffPayroll {
            $member = StaffProfile::query()->lockForUpdate()->findOrFail($data['staff_profile_id']);
            $this->assertRecordBranch($actor, $member->branch_id);
            abort_unless($member->status === 'active', 422, 'Payroll can only be prepared for active staff.');
            $basic = round((float) $member->base_salary, 2);
            $allowances = round((float) ($data['allowances'] ?? 0), 2);
            $overtimeHours = round((float) ($data['overtime_hours'] ?? 0), 2);
            $overtimeRate = round((float) ($data['overtime_rate'] ?? 0), 2);
            $overtimeAmount = round($overtimeHours * $overtimeRate, 2);
            $bonus = round((float) ($data['bonus'] ?? 0), 2);
            $deductions = round((float) ($data['deductions'] ?? 0), 2);
            $gross = round($basic + $allowances + $overtimeAmount, 2);
            abort_if($deductions > $gross + $bonus, 422, 'Deductions cannot exceed gross salary plus bonus.');
            abort_if(StaffPayroll::query()->where('staff_profile_id', $member->id)->where('payroll_month', $data['payroll_month'])->exists(), 409, 'Payroll already exists for this staff member and month.');
            return StaffPayroll::query()->create([
                'staff_profile_id' => $member->id, 'branch_id' => $member->branch_id, 'payroll_month' => $data['payroll_month'],
                'basic_salary' => $basic, 'allowances' => $allowances, 'overtime_hours' => $overtimeHours,
                'overtime_rate' => $overtimeRate, 'overtime_amount' => $overtimeAmount,
                'gross_salary' => $gross, 'bonus' => $bonus, 'deductions' => $deductions,
                'net_paid' => round($gross + $bonus - $deductions, 2), 'status' => 'pending',
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
            $locked->forceFill(['status' => 'paid', 'paid_at' => now(), 'payment_reference' => trim($data['payment_reference'])])->save();
            return $locked->fresh();
        }, 3);
        $this->audit($request, 'hr.payroll_marked_paid', 'Confirmed payroll #'.$record->id.' with a disbursement reference.');
        return back()->with('success', 'Payroll marked as paid.');
    }

    private function scoped($query, User $actor)
    {
        return $actor->isBranchManager() ? $query->where('branch_id', $actor->branch_id) : $query;
    }
    private function assertBranchAssigned(User $actor): void
    {
        if ($actor->isBranchManager()) abort_unless($actor->branch_id !== null && DB::table('branches')->where('id', $actor->branch_id)->where('is_active', true)->exists(), 403, 'Branch manager must be assigned to an active branch.');
    }
    private function assertRecordBranch(User $actor, ?int $branchId): void
    {
        if ($actor->isBranchManager()) abort_unless((int) $actor->branch_id === (int) $branchId, 404);
    }
    private function audit(Request $request, string $event, string $description): void
    {
        try {
            ActivityLog::query()->create(['user_id' => $request->user()?->id, 'event' => $event, 'description' => mb_substr($description, 0, 255),
                'method' => $request->method(), 'route' => optional($request->route())->getName(), 'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000)]);
        } catch (\Throwable $exception) { report($exception); }
    }
}
