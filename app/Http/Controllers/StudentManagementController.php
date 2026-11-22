<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\StudentLifecycleEvent;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'suspended', 'graduated', 'transferred'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $branchManager = $request->user()->isBranchManager();\n        abort_if($branchManager && !$request->user()->branch_id, 403, 'An active branch assignment is required.');\n        $students = User::query()->where('role', 'student')\n            ->when($branchManager, fn ($q) => $q->where('branch_id', $request->user()->branch_id))
            ->with(['branch', 'studentProfile'])
            ->withCount(['enrollments', 'guardianLinksAsStudent'])
            ->when($filters['search'] ?? null, function ($query, $term): void {
                $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('phone_number', 'like', '%'.$term.'%'));
            })
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->whereHas('studentProfile', fn ($p) => $p->where('lifecycle_status', $status)))
            ->latest()->paginate(25)->withQueryString();

        $routePrefix = $branchManager ? 'branch.students.manage' : 'admin.students';\n        return view('admin.students.index', compact('students', 'filters', 'routePrefix'));
    }

    public function show(User $student): View
    {
        abort_unless($student->isStudent(), 404);
        $student->load(['branch', 'studentProfile.suspendedBy', 'enrollments.batch.branch', 'enrollments.course', 'guardianLinksAsStudent.guardian']);
        $history = StudentLifecycleEvent::query()->where('student_id', $student->id)
            ->with('actor:id,name')->latest()->paginate(30);
        $batches = Batch::query()->where('status', 'open')->where('course_id', $student->enrollments()->where('status', 'active')->value('course_id'))\n            ->when(request()->user()->isBranchManager(), fn ($q) => $q->where('branch_id', request()->user()->branch_id))
            ->with('branch')->orderBy('starts_at')->get();

        return view('admin.students.show', compact('student', 'history', 'batches', 'routePrefix'));
    }

    public function updateProfile(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $data = $request->validate([
            'student_number' => ['nullable', 'string', 'max:40', Rule::unique('student_profiles', 'student_number')->ignore($student->studentProfile?->id)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'blood_group' => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'previous_school' => ['nullable', 'string', 'max:180'],
            'admission_date' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($student, $data, $request): void {
            $profile = StudentProfile::query()->firstOrNew(['user_id' => $student->id]);
            $before = $profile->exists ? $profile->only(array_keys($data)) : [];
            $profile->fill($data)->save();
            $this->event($student, $request, 'profile_updated', $before, $profile->only(array_keys($data)), 'Student profile updated.');
        });

        return back()->with('success', 'Student profile updated.');
    }

    public function transferBatch(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $this->authorizeStudentScope($request, $student);
        $data = $request->validate([
            'enrollment_id' => ['required', 'integer'],
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'confirm_branch_change' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($student, $data, $request): void {
            $enrollment = Enrollment::query()->whereKey($data['enrollment_id'])
                ->where('user_id', $student->id)->lockForUpdate()->firstOrFail();
            abort_unless($enrollment->status === 'active', 422, 'Only active enrollments can be transferred.');
            $target = Batch::query()->whereKey($data['batch_id'])->where('status', 'open')->lockForUpdate()->firstOrFail();
            abort_unless((int) $target->course_id === (int) $enrollment->course_id, 422, 'The target batch must belong to the same course.');
            $branchChanged = (int) $target->branch_id !== (int) $student->branch_id;
            if ($branchChanged) {
                abort_unless($request->boolean('confirm_branch_change'), 422, 'Confirm the branch change before transferring this student.');
                abort_if($request->user()->isBranchManager(), 403, 'Branch managers cannot transfer students outside their assigned branch.');
            }
            if ($target->capacity !== null) {
                $occupied = $target->enrollments()->where('status', 'active')->where('id', '!=', $enrollment->id)->count();
                abort_if($occupied >= $target->capacity, 422, 'The target batch is full.');
            }
            $old = ['batch_id' => $enrollment->batch_id, 'branch_id' => $student->branch_id, 'status' => $enrollment->status];
            $enrollment->update(['batch_id' => $target->id]);
            if ($branchChanged) {
                $student->update(['branch_id' => $target->branch_id]);
            }
            $this->event($student, $request, 'batch_transferred', $old, [
                'batch_id' => $target->id,
                'branch_id' => $target->branch_id,
                'branch_changed' => $branchChanged,
            ], $data['reason']);
        });

        return back()->with('success', 'Student batch transfer completed.');
    }

    public function promote(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $data = $request->validate(['level' => ['required', 'string', 'max:100'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($student, $data, $request): void {
            $profile = StudentProfile::query()->firstOrCreate(['user_id' => $student->id]);
            $before = ['level' => $profile->academic_level ?? null];
            $profile->forceFill(['academic_level' => $data['level']])->save();
            $this->event($student, $request, 'promoted', $before, ['level' => $data['level']], $data['reason']);
        });
        return back()->with('success', 'Student promotion recorded.');
    }

    public function suspend(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($student, $data, $request): void {
            $profile = StudentProfile::query()->firstOrCreate(['user_id' => $student->id]);
            abort_if($profile->lifecycle_status === 'suspended', 409, 'Student is already suspended.');
            $before = ['lifecycle_status' => $profile->lifecycle_status];
            $profile->forceFill(['lifecycle_status' => 'suspended', 'suspended_at' => now(), 'suspended_by' => $request->user()->id, 'suspension_reason' => $data['reason']])->save();
            $this->event($student, $request, 'suspended', $before, ['lifecycle_status' => 'suspended'], $data['reason']);
        });
        return back()->with('success', 'Student suspended and recorded in history.');
    }

    public function reactivate(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        DB::transaction(function () use ($student, $request): void {
            $profile = StudentProfile::query()->where('user_id', $student->id)->lockForUpdate()->firstOrFail();
            abort_unless($profile->lifecycle_status === 'suspended', 409);
            $profile->forceFill(['lifecycle_status' => 'active', 'suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null])->save();
            $this->event($student, $request, 'reactivated', ['lifecycle_status' => 'suspended'], ['lifecycle_status' => 'active'], 'Student reactivated by administrator.');
        });
        return back()->with('success', 'Student account reactivated.');
    }

    private function authorizeStudentScope(Request $request, User $student): void
    {
        if ($request->user()->isBranchManager()) {
            abort_if(!$request->user()->branch_id || (int) $student->branch_id !== (int) $request->user()->branch_id, 404);
        }
    }

    private function event(User $student, Request $request, string $type, array $before, array $after, string $reason): void
    {
        StudentLifecycleEvent::create(['student_id' => $student->id, 'actor_id' => $request->user()->id, 'event_type' => $type, 'before_data' => $before, 'after_data' => $after, 'reason' => $reason]);
    }
}
