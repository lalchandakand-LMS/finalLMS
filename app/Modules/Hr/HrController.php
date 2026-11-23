<?php

declare(strict_types=1);

namespace App\Modules\Hr;

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
        $linkableUsers = User::query()->whereIn('role', ['teacher', 'branch_manager', 'staff'])
            ->when($actor->isBranchManager(), fn ($query) => $query->where(function ($builder) use ($actor): void {
                $builder->whereNull('branch_id')->orWhere('branch_id', $actor->branch_id);
            }))
            ->orderBy('name')->get(['id', 'name', 'email', 'role']);

        return view('modules.hr.index', [
            'linkableUsers' => $linkableUsers,
            'staff' => $staffQuery->orderBy('full_name')->paginate(25, ['*'], 'staff_page')->withQueryString(),
            'payrolls' => $payrollQuery->latest()->limit(60)->get(),
            'attendances' => $attendanceQuery->whereDate('attendance_date', '>=', now()->subDays(30)->toDateString())->latest('attendance_date')->limit(100)->get(),
            'leaveRequests' => $leaveQuery->latest()->limit(100)->get(),
        ]);
    }

    public function publicCareers(): View
    {
        $openings = StaffJobOpening::query()->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>=', now()))
            ->latest()->paginate(20);

        return view('modules.hr.careers.index', compact('openings'));
    }

    public function publicCareerShow(int $opening): View
    {
        $opening = StaffJobOpening::query()->findOrFail($opening);
        abort_unless($opening->status === 'published' && (! $opening->closes_at || $opening->closes_at->isFuture()), 404);

        return view('modules.hr.careers.show', compact('opening'));
    }

    public function applyCareer(Request $request, int $opening): RedirectResponse
    {
        $opening = StaffJobOpening::query()->findOrFail($opening);
        abort_unless($opening->status === 'published' && (! $opening->closes_at || $opening->closes_at->isFuture()), 404);
        $data = $request->validate([
            'applicant_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);
        $resumePath = $request->file('resume')->store('private/hr-applications', 'local');
        try {
            StaffApplication::query()->create([
                'staff_job_opening_id' => $opening->id,
                'applicant_name' => $data['applicant_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'cover_letter' => $data['cover_letter'] ?? null,
                'resume_path' => $resumePath,
                'status' => 'submitted',
            ]);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($resumePath);
            throw $exception;
        }

        return back()->with('success', 'Your application has been submitted successfully.');
    }

    public function recruitmentIndex(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $openings = StaffJobOpening::query()->withCount('applications')->latest()->paginate(20, ['*'], 'opening_page');
        $applications = StaffApplication::query()->with('opening')->latest()->paginate(30, ['*'], 'application_page');

        return view('modules.hr.recruitment', compact('openings', 'applications'));
    }

    public function storeJobOpening(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin(), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'department' => ['required', 'string', 'max:100'],
            'designation' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:12000'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,temporary'],
            'location' => ['nullable', 'string', 'max:160'],
            'closes_at' => ['nullable', 'date', 'after:now'],
            'status' => ['required', 'in:draft,published'],
        ]);
        StaffJobOpening::query()->create($data + ['created_by' => $actor->id]);
        $this->audit($request, 'hr.job_opening_created', 'Created recruitment opening '.$data['title']);

        return back()->with('success', 'Job opening created.');
    }

    public function updateJobOpeningStatus(Request $request, int $opening): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin(), 403);
        $data = $request->validate(['status' => ['required', 'in:draft,published,closed']]);
        $job = StaffJobOpening::query()->findOrFail($opening);
        $job->update(['status' => $data['status']]);
        $this->audit($request, 'hr.job_opening_status_changed', 'Updated recruitment opening #'.$job->id.' to '.$job->status);

        return back()->with('success', 'Job opening status updated.');
    }

    public function reviewApplication(Request $request, int $application): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin(), 403);
        $data = $request->validate([
            'status' => ['required', 'in:shortlisted,interview,approved,rejected,hired'],
            'review_notes' => ['nullable', 'string', 'max:3000'],
            'employee_code' => ['required_if:status,hired', 'nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/D', 'unique:staff_profiles,employee_code'],
            'base_salary' => ['required_if:status,hired', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        if (! empty($data['branch_id'])) {
            abort_unless(DB::table('branches')->where('id', $data['branch_id'])->where('is_active', true)->exists(), 422, 'Select an active branch.');
        }
        DB::transaction(function () use ($application, $data, $actor): void {
            $record = StaffApplication::query()->lockForUpdate()->findOrFail($application);
            abort_unless(! in_array($record->status, ['rejected', 'hired'], true), 409, 'This application has already been closed.');
            if ($data['status'] === 'hired') {
                StaffProfile::query()->create([
                    'employee_code' => $data['employee_code'],
                    'full_name' => $record->applicant_name,
                    'designation' => $record->opening->designation,
                    'department' => $record->opening->department,
                    'phone' => $record->phone,
                    'email' => $record->email,
                    'base_salary' => $data['base_salary'],
                    'status' => 'active',
                    'branch_id' => $data['branch_id'] ?? null,
                    'employment_type' => $record->opening->employment_type,
                    'joined_at' => now()->toDateString(),
                ]);
            }
            $record->forceFill([
                'status' => $data['status'],
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();
        }, 3);
        $this->audit($request, 'hr.application_reviewed', 'Reviewed recruitment application #'.$application.' as '.$data['status']);

        return back()->with('success', 'Application status updated.');
    }

    public function downloadApplicationResume(int $application)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $record = StaffApplication::query()->findOrFail($application);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($record->resume_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($record->resume_path, 'application-'.$record->id.'-resume');
    }

    public function teacherIndex(Request $request): View
    {
        $teacher = $request->user();
        abort_unless($teacher && $teacher->isTeacher(), 403);
        $profile = StaffProfile::query()->where('user_id', $teacher->id)->firstOrFail();
        $attendances = $profile->attendances()->latest('attendance_date')->limit(60)->get();
        $leaveRequests = $profile->leaveRequests()->latest()->limit(30)->get();

        return view('modules.hr.teacher', compact('profile', 'attendances', 'leaveRequests'));
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
            return StaffLeaveRequest::query()->create($data + ['staff_profile_id' => $lockedProfile->id, 'branch_id' => $lockedProfile->branch_id, 'status' => 'pending']);
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
            'compensation_basis' => ['nullable', 'in:monthly,monthly_attendance,per_class'],
            'per_class_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'working_days_per_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'status' => ['required', 'in:active,on_leave,inactive'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:staff_profiles,user_id', 'required_if:compensation_basis,per_class'],
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
            abort_unless(($data['compensation_basis'] ?? 'monthly') !== 'per_class' || $linkedUser->role === 'teacher', 422, 'Per-class compensation can only be linked to a teacher account.');
            abort_unless(in_array($linkedUser->role, ['teacher', 'branch_manager', 'staff'], true), 422, 'Link only a teacher, branch manager or staff account.');
            if ($actor->isBranchManager()) {
                abort_unless($linkedUser->branch_id === null || (int) $linkedUser->branch_id === (int) $actor->branch_id, 422, 'You can only link an account assigned to your branch.');
            }
            if (! empty($data['branch_id'])) {
                abort_unless($linkedUser->branch_id === null || (int) $linkedUser->branch_id === (int) $data['branch_id'], 422, 'The linked account belongs to another branch.');
            }
        }

        $data['compensation_basis'] = $data['compensation_basis'] ?? 'monthly';
        $data['per_class_rate'] = $data['per_class_rate'] ?? 0;
        $data['working_days_per_month'] = $data['working_days_per_month'] ?? 26;
        abort_if($data['compensation_basis'] === 'per_class' && (float) $data['per_class_rate'] <= 0, 422, 'Set a per-class rate greater than zero.');
        $member = StaffProfile::query()->create($data + ['joined_at' => now()->toDateString()]);
        $this->audit($request, 'hr.staff_created', 'Created staff profile '.$member->employee_code);

        return back()->with('success', 'Staff profile created.');
    }


    public function updateStaff(Request $request, int $staff): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $member = StaffProfile::query()->findOrFail($staff);
        $this->assertRecordBranch($actor, $member->branch_id);

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/D', Rule::unique('staff_profiles', 'employee_code')->ignore($member->id)],
            'full_name' => ['required', 'string', 'max:160'],
            'designation' => ['required', 'string', 'max:120'],
            'department' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'compensation_basis' => ['required', 'in:monthly,monthly_attendance,per_class'],
            'per_class_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'working_days_per_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'status' => ['required', 'in:active,on_leave,inactive'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('staff_profiles', 'user_id')->ignore($member->id), 'required_if:compensation_basis,per_class'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,temporary'],
            'contract_ends_at' => ['nullable', 'date'],
        ]);

        if ($actor->isBranchManager()) {
            $data['branch_id'] = $actor->branch_id;
        } elseif (! empty($data['branch_id'])) {
            abort_unless(DB::table('branches')->where('id', $data['branch_id'])->where('is_active', true)->exists(), 422, 'Select an active branch.');
        }
        if (! empty($data['user_id'])) {
            $linkedUser = User::query()->findOrFail($data['user_id']);
            abort_unless(in_array($linkedUser->role, ['teacher', 'branch_manager', 'staff'], true), 422, 'Link only a teacher, branch manager or staff account.');
            abort_unless($data['compensation_basis'] !== 'per_class' || $linkedUser->role === 'teacher', 422, 'Per-class compensation can only be linked to a teacher account.');
            if ($actor->isBranchManager()) {
                abort_unless($linkedUser->branch_id === null || (int) $linkedUser->branch_id === (int) $actor->branch_id, 422, 'You can only link an account assigned to your branch.');
            }
            if (! empty($data['branch_id'])) {
                abort_unless($linkedUser->branch_id === null || (int) $linkedUser->branch_id === (int) $data['branch_id'], 422, 'The linked account belongs to another branch.');
            }
        }
        $data['per_class_rate'] = $data['per_class_rate'] ?? 0;
        $data['working_days_per_month'] = $data['working_days_per_month'] ?? 26;
        abort_if($data['compensation_basis'] === 'per_class' && (float) $data['per_class_rate'] <= 0, 422, 'Set a per-class rate greater than zero.');

        $member->update($data);
        $this->audit($request, 'hr.staff_updated', 'Updated staff profile '.$member->employee_code.'; existing payroll snapshots remain unchanged.');

        return back()->with('success', 'Staff profile updated. Existing payroll records are unchanged.');
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
            abort_if(StaffPayroll::query()->where('staff_profile_id', $member->id)->where('payroll_month', $data['payroll_month'])->exists(), 409, 'Payroll already exists for this staff member and month.');

            $periodStart = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $data['payroll_month'].'-01');
            $periodEnd = $periodStart->endOfMonth();
            $attendanceQuery = StaffAttendance::query()
                ->where('staff_profile_id', $member->id)
                ->whereBetween('attendance_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
            $attendanceRecordCount = (int) (clone $attendanceQuery)->count();
            $attendance = $attendanceQuery
                ->where('staff_profile_id', $member->id)
                ->whereBetween('attendance_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->selectRaw("SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) as present_days")
                ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days")
                ->selectRaw("SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days")
                ->selectRaw("SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as leave_days")
                ->first();

            $presentDays = (float) ($attendance->present_days ?? 0);
            $absentDays = (float) ($attendance->absent_days ?? 0);
            $halfDays = (float) ($attendance->half_days ?? 0);
            $leaveDays = (float) DB::table('staff_attendances as attendance')
                ->join('staff_leave_requests as leave_requests', function ($join): void {
                    $join->on('leave_requests.staff_profile_id', '=', 'attendance.staff_profile_id')
                        ->where('leave_requests.status', '=', 'approved')
                        ->where('leave_requests.leave_type', '=', 'unpaid')
                        ->whereColumn('leave_requests.starts_on', '<=', 'attendance.attendance_date')
                        ->whereColumn('leave_requests.ends_on', '>=', 'attendance.attendance_date');
                })
                ->where('attendance.staff_profile_id', $member->id)
                ->where('attendance.status', 'on_leave')
                ->whereBetween('attendance.attendance_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->distinct('attendance.attendance_date')
                ->count('attendance.attendance_date');
            $classesDelivered = 0;
            $basis = $member->compensation_basis ?: 'monthly';
            $workingDays = max(1, (int) ($member->working_days_per_month ?: 26));

            if ($basis === 'per_class') {
                abort_if($periodEnd->isFuture(), 422, 'Class-based payroll can only be finalized after the selected month ends.');
                abort_unless($member->user_id, 422, 'Per-class payroll requires this staff profile to be linked to a teacher user account.');
                abort_if((float) $member->per_class_rate <= 0, 422, 'Set a per-class rate greater than zero before preparing payroll.');
                $classesDelivered = (int) DB::table('live_classes')
                    ->join('batches', 'batches.id', '=', 'live_classes.batch_id')
                    ->where('batches.teacher_id', $member->user_id)
                    ->where('live_classes.status', 'completed')
                    ->whereBetween('live_classes.starts_at', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()])
                    ->count();
                abort_if($classesDelivered === 0, 422, 'No completed classes were found for this teacher in the selected payroll month.');
                $basic = round($classesDelivered * (float) $member->per_class_rate, 2);
            } else {
                $basic = round((float) $member->base_salary, 2);
            }

            $attendanceDeduction = 0.0;
            if ($basis === 'monthly_attendance') {
                abort_if($periodEnd->isFuture(), 422, 'Attendance-based payroll can only be finalized after the selected month ends.');
                abort_unless(
                    $attendanceRecordCount === $workingDays,
                    422,
                    'Attendance is incomplete for '.$data['payroll_month'].': '.$attendanceRecordCount.' of '.$workingDays.' expected working-day records are saved.'
                );
                $dailyRate = round((float) $member->base_salary / $workingDays, 6);
                // Only recorded absences and half-days affect pay. Approved unpaid
                // leave must be marked as on_leave in the staff attendance register.
                $attendanceDeduction = round(($absentDays + ($halfDays * 0.5) + $leaveDays) * $dailyRate, 2);
            }

            $allowances = round((float) ($data['allowances'] ?? 0), 2);
            $overtimeHours = round((float) ($data['overtime_hours'] ?? 0), 2);
            $overtimeRate = round((float) ($data['overtime_rate'] ?? 0), 2);
            $overtimeAmount = round($overtimeHours * $overtimeRate, 2);
            $bonus = round((float) ($data['bonus'] ?? 0), 2);
            $manualDeductions = round((float) ($data['deductions'] ?? 0), 2);
            $gross = round($basic + $allowances + $overtimeAmount, 2);
            $totalDeductions = round($attendanceDeduction + $manualDeductions, 2);
            abort_if($totalDeductions > $basic + $allowances + $overtimeAmount + $bonus, 422, 'Total deductions cannot exceed the payable salary.');

            return StaffPayroll::query()->create([
                'staff_profile_id' => $member->id,
                'branch_id' => $member->branch_id,
                'payroll_month' => $data['payroll_month'],
                'basic_salary' => $basic,
                'allowances' => $allowances,
                'overtime_hours' => $overtimeHours,
                'overtime_rate' => $overtimeRate,
                'overtime_amount' => $overtimeAmount,
                'gross_salary' => $gross,
                'bonus' => $bonus,
                'deductions' => $totalDeductions,
                'net_paid' => round($gross + $bonus - $totalDeductions, 2),
                'compensation_basis_snapshot' => $basis,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'half_days' => $halfDays,
                'unpaid_leave_days' => $leaveDays,
                'payable_days' => max(0, $presentDays + ($halfDays * 0.5)),
                'classes_delivered' => $classesDelivered,
                'attendance_deduction' => $attendanceDeduction,
                'attendance_records_count' => $attendanceRecordCount,
                'expected_working_days' => $basis === 'monthly_attendance' ? $workingDays : 0,
                'status' => 'pending',
            ]);
        }, 3);
        $this->audit($request, 'hr.payroll_recorded', 'Prepared payroll #'.$payroll->id.' for '.$payroll->payroll_month.' using '.$payroll->compensation_basis_snapshot.' calculation.');
        return back()->with('success', 'Payroll calculated from the saved attendance and class records; awaiting disbursement confirmation.');
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
