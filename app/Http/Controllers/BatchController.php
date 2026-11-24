<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\AcademicLevel;
use App\Models\Branch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['academic_level_id' => ['nullable', 'integer', 'exists:academic_levels,id']]);
        $branches = Branch::withCount(['batches', 'users'])->orderBy('name')->get();
        $batches = Batch::with(['branch', 'course.academicLevel', 'teacher'])
            ->when($filters['academic_level_id'] ?? null, fn ($query, $levelId) => $query->whereHas('course', fn ($courses) => $courses->where('academic_level_id', $levelId)))
            ->latest('starts_at')->get();
        $courses = Course::with('academicLevel')
            ->when($filters['academic_level_id'] ?? null, fn ($query, $levelId) => $query->where('academic_level_id', $levelId))
            ->orderBy('title')->get();
        $teachers = User::where('role', 'teacher')->orderBy('name')->get();
        $academicLevels = AcademicLevel::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.batches.index', compact('branches', 'batches', 'courses', 'teachers', 'academicLevels', 'filters'));
    }

    public function branchManagerIndex(Request $request): View
    {
        $branchId = $request->user()->branch_id;
        abort_unless($branchId && Branch::whereKey($branchId)->where('is_active', true)->where('status', 'active')->exists(), 403, 'No active branch is assigned to this account.');

        $batches = Batch::with(['branch', 'course', 'teacher'])->withCount('enrollments')
            ->where('branch_id', $branchId)->latest('starts_at')->paginate(25);
        $courses = Course::where('status', 'published')->orderBy('title')->get();
        $teachers = User::where('role', 'teacher')->where('branch_id', $branchId)->orderBy('name')->get();

        return view('branch-manager.batches.index', compact('batches', 'courses', 'teachers'));
    }

    public function branchManagerStore(Request $request): RedirectResponse
    {
        $branchId = $request->user()->branch_id;
        abort_unless($branchId && Branch::whereKey($branchId)->where('is_active', true)->where('status', 'active')->exists(), 403, 'No active branch is assigned to this account.');
        $validated = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')->where(fn ($query) => $query->where('status', 'published'))],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'teacher')->where('branch_id', $branchId))],
            'title' => ['required', 'string', 'max:160'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'fee' => ['required', 'numeric', 'min:0'],
            'fee_calculation_mode' => ['nullable', 'in:fixed,per_class'],
            'per_class_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
        $validated = $this->normalizeFeeMode($validated);
        Batch::create([...$validated, 'branch_id' => $branchId, 'status' => 'open']);
        return redirect()->route('branch-manager.batches.index')->with('success', 'Batch created for your branch.');
    }

    public function branchManagerUpdate(Batch $batch, Request $request): RedirectResponse
    {
        $branchId = $request->user()->branch_id;
        abort_unless($branchId && (int) $batch->branch_id === (int) $branchId
            && Branch::whereKey($branchId)->where('is_active', true)->where('status', 'active')->exists(),
            404, 'The batch is not available to this active branch.');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'fee' => ['required', 'numeric', 'min:0'],
            'fee_calculation_mode' => ['nullable', 'in:fixed,per_class'],
            'per_class_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'status' => ['required', 'in:open,closed,completed'],
        ]);
        abort_if($validated['capacity'] < $batch->enrollments()->whereIn('status', ['active', 'completed'])->count(), 422, 'Capacity cannot be lower than the current enrolled student count.');
        $batch->update($this->normalizeFeeMode($validated, $batch));
        return redirect()->route('branch-manager.batches.index')->with('success', 'Your branch batch was updated.');
    }

    public function branchManagerStudents(Request $request): View
    {
        $branchId = $request->user()->branch_id;
        abort_unless($branchId && Branch::whereKey($branchId)->where('is_active', true)->where('status', 'active')->exists(), 403, 'No active branch is assigned to this account.');
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $students = User::query()->where('role', 'student')
            ->where(function ($query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereHas('enrollments', fn ($enrollments) => $enrollments
                    ->whereIn('status', ['active', 'completed'])->whereHas('batch', fn ($batch) => $batch->where('branch_id', $branchId)));
            })
            ->with(['enrollments' => function ($query) use ($branchId): void {
                $query->whereIn('status', ['active', 'completed'])->whereHas('batch', fn ($batch) => $batch->where('branch_id', $branchId))->with(['batch.course']);
            }])
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(fn ($builder) => $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')->orWhere('phone_number', 'like', '%'.$search.'%'));
            })->orderBy('name')->paginate(25)->withQueryString();

        return view('branch-manager.students.index', compact('students'));
    }

    public function teacherIndex(): View
    {
        $batches = Batch::with(['branch', 'course'])->where('teacher_id', Auth::id())->latest('starts_at')->get();
        return view('teacher.batches.index', compact('batches'));
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:branches,name'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
            'registration_open' => ['sometimes', 'boolean'],
        ]);
        Branch::create([
            ...$validated,
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
            'is_active' => $validated['status'] === 'active',
        ]);
        return redirect()->route('admin.batches.index')->with('success', 'Branch created successfully.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->ignore($branch->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
            'registration_open' => ['sometimes', 'boolean'],
        ]);
        $validated['status'] = $validated['status'] ?? $branch->status ?? ($branch->is_active ? 'active' : 'inactive');
        $validated['registration_open'] = (bool) ($validated['registration_open'] ?? $branch->registration_open ?? true);
        $validated['is_active'] = $validated['status'] === 'active';
        $branch->update($validated);
        return redirect()->route('admin.batches.index')->with('success', 'Branch settings updated successfully.');
    }

    public function destroyBranch(Branch $branch): RedirectResponse
    {
        if ($branch->batches()->exists()) {
            return back()->with('error', 'Move or delete this branch’s batches before deleting the branch.');
        }
        if (User::where('branch_id', $branch->id)->exists()) {
            return back()->with('error', 'Reassign staff and students before deleting this branch.');
        }
        $branch->delete();
        return back()->with('success', 'Branch deleted successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('is_active', true)->where('status', 'active'))],
            'course_id' => ['required', 'exists:courses,id'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'teacher'))],
            'title' => ['required', 'string', 'max:160'], 'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'fee' => ['required', 'numeric', 'min:0'],
            'fee_calculation_mode' => ['nullable', 'in:fixed,per_class'],
            'per_class_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
        $validated = $this->normalizeFeeMode($validated);
        Batch::create([...$validated, 'status' => 'open']);
        return redirect()->route('admin.batches.index')->with('success', 'Batch created successfully.');
    }

    public function update(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('is_active', true)->where('status', 'active'))],
            'course_id' => ['required', 'exists:courses,id'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'teacher'))],
            'title' => ['required', 'string', 'max:160'], 'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'fee' => ['required', 'numeric', 'min:0'],
            'fee_calculation_mode' => ['nullable', 'in:fixed,per_class'],
            'per_class_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'status' => ['required', 'in:open,closed,completed'],
        ]);
        $batch->update($this->normalizeFeeMode($validated, $batch));
        return redirect()->route('admin.batches.index')->with('success', 'Batch updated successfully.');
    }

    private function normalizeFeeMode(array $validated, ?Batch $batch = null): array
    {
        $mode = $validated['fee_calculation_mode'] ?? $batch?->fee_calculation_mode ?? 'fixed';
        $perClassFee = $validated['per_class_fee'] ?? $batch?->per_class_fee;

        abort_if($mode === 'per_class' && (float) $perClassFee <= 0, 422, 'Set a per-class fee greater than zero.');

        $validated['fee_calculation_mode'] = $mode;
        $validated['per_class_fee'] = $mode === 'per_class' ? $perClassFee : null;

        return $validated;
    }

    public function destroy(Batch $batch): RedirectResponse
    {
        $batch->delete();
        return back()->with('success', 'Batch deleted successfully.');
    }
}
