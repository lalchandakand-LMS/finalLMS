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
    public function export(Request $request): \\Symfony\\Component\\HttpFoundation\\StreamedResponse
    {
        $query = User::query()->where('role', 'student')->with(['studentProfile', 'branch'])
            ->withCount(['guardianLinksAsStudent', 'enrollments'])
            ->when($request->user()->isBranchManager(), fn ($q) => $q->where('branch_id', $request->user()->branch_id));
        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\\xEF\\xBB\\xBF");
            fputcsv($out, ['name','email','phone','branch','student_number','status','academic_level','date_of_birth','admission_date','guardians','enrollments']);
            $query->orderBy('id')->chunk(500, function ($students) use ($out): void {
                foreach ($students as $student) {
                    $profile = $student->studentProfile;
                    $values = [$student->name,$student->email,$student->phone_number,$student->branch?->name,$profile?->student_number,$profile?->lifecycle_status ?? 'active',$profile?->academic_level,$profile?->date_of_birth?->format('Y-m-d'),$profile?->admission_date?->format('Y-m-d'),$student->guardian_links_as_student_count,$student->enrollments_count];
                    fputcsv($out, array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\\-\\t\\r]/', $value) ? "'".$value : $value, $values));
                }
            });
            fclose($out);
        }, 'students-export-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importPreview(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required','file','mimes:csv,txt','max:2048']]);
        $stream = fopen($request->file('file')->getRealPath(), 'r');
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($stream) ?: []);
        $allowed = ['email','student_number','date_of_birth','gender','blood_group','nationality','address_line','city','district','emergency_contact_name','emergency_contact_phone','previous_school','admission_date','academic_level'];
        abort_unless(in_array('email', $headers, true), 422, 'CSV must contain an email column.');
        $rows = []; $errors = []; $line = 1;
        while (($values = fgetcsv($stream)) !== false && count($rows) + count($errors) < 500) {
            $line++;
            if (count($values) !== count($headers)) { $errors[] = "Row {$line}: column count mismatch."; continue; }
            $row = array_combine($headers, $values);
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $student = User::query()->where('role','student')->where('email',$email)
                ->when($request->user()->isBranchManager(), fn ($q) => $q->where('branch_id',$request->user()->branch_id))->first();
            if (!$student) { $errors[] = "Row {$line}: no student account found for {$email} in your scope."; continue; }
            $data = array_intersect_key($row, array_flip($allowed));
            $validator = \\Illuminate\\Support\\Facades\\Validator::make($data, [
                'student_number' => ['nullable','string','max:40',Rule::unique('student_profiles','student_number')->where(fn ($q) => $q->where('user_id','!=',$student->id))],
                'date_of_birth' => ['nullable','date','before:today'], 'gender' => ['nullable','string','max:30'],
                'blood_group' => ['nullable','in:A+,A-,B+,B-,AB+,AB-,O+,O-'], 'nationality' => ['nullable','string','max:80'],
                'address_line' => ['nullable','string','max:255'], 'city' => ['nullable','string','max:100'], 'district' => ['nullable','string','max:100'],
                'emergency_contact_name' => ['nullable','string','max:160'], 'emergency_contact_phone' => ['nullable','string','max:40'],
                'previous_school' => ['nullable','string','max:180'], 'admission_date' => ['nullable','date'], 'academic_level' => ['nullable','string','max:100'],
            ]);
            if ($validator->fails()) { $errors[] = "Row {$line}: ".implode('; ', $validator->errors()->all()); continue; }
            $rows[] = ['student_id' => $student->id, 'data' => $data];
        }
        fclose($stream);
        $request->session()->put('student_import_preview', ['actor_id' => $request->user()->id, 'rows' => $rows, 'errors' => $errors, 'created_at' => now()->timestamp]);
        return back()->with('student_import_summary', ['valid' => count($rows), 'errors' => $errors]);
    }

    public function importConfirm(Request $request): RedirectResponse
    {
        $preview = $request->session()->get('student_import_preview');
        abort_unless(is_array($preview) && (int) ($preview['actor_id'] ?? 0) === (int) $request->user()->id
            && now()->timestamp - (int) ($preview['created_at'] ?? 0) <= 1800, 419, 'Import preview expired. Upload the CSV again.');
        DB::transaction(function () use ($preview, $request): void {
            foreach ($preview['rows'] as $row) {
                $student = User::query()->where('role','student')->whereKey($row['student_id'])
                    ->when($request->user()->isBranchManager(), fn ($q) => $q->where('branch_id',$request->user()->branch_id))
                    ->lockForUpdate()->firstOrFail();
                $profile = StudentProfile::query()->firstOrNew(['user_id' => $student->id]);
                $before = $profile->exists ? $profile->only(array_keys($row['data'])) : [];
                $profile->fill($row['data'])->save();
                $this->event($student, $request, 'bulk_profile_imported', $before, $profile->only(array_keys($row['data'])), 'Student profile imported from reviewed CSV.');
            }
        });
        $request->session()->forget('student_import_preview');
        return back()->with('success', count($preview['rows']).' student profiles imported. '.count($preview['errors']).' rows were skipped.');
    }

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

    public function graduate(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $this->authorizeStudentScope($request, $student);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($student, $data, $request): void {
            $profile = StudentProfile::query()->firstOrCreate(['user_id' => $student->id]);
            abort_unless($profile->lifecycle_status === 'active', 409, 'Only active students can be graduated.');
            $profile->forceFill(['lifecycle_status' => 'graduated'])->save();
            $this->event($student, $request, 'graduated', ['lifecycle_status' => 'active'], ['lifecycle_status' => 'graduated'], $data['reason']);
        });
        return back()->with('success', 'Student graduation recorded.');
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
