<?php

namespace App\Http\Controllers;

use App\Models\Batch;
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
    public function index(): View
    {
        $branches = Branch::withCount('batches')->orderBy('name')->get();
        $batches = Batch::with(['branch', 'course', 'teacher'])->latest('starts_at')->get();
        $courses = Course::orderBy('title')->get();
        $teachers = User::where('role', 'teacher')->orderBy('name')->get();

        return view('admin.batches.index', compact('branches', 'batches', 'courses', 'teachers'));
    }

    public function branchManagerIndex(Request $request): View
    {
        abort_unless($request->user()->branch_id, 403, 'No branch is assigned to this account.');

        $batches = Batch::with(['branch', 'course', 'teacher'])
            ->where('branch_id', $request->user()->branch_id)
            ->latest('starts_at')
            ->paginate(25);

        return view('branch-manager.batches.index', compact('batches'));
    }

    public function teacherIndex(): View
    {
        $batches = Batch::with(['branch', 'course'])
            ->where('teacher_id', Auth::id())
            ->latest('starts_at')
            ->get();

        return view('teacher.batches.index', compact('batches'));
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:branches,name'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        Branch::create([
            ...$validated,
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        return redirect()->route('admin.batches.index')->with('success', 'Branch created successfully.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->ignore($branch->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['required', 'boolean'],
        ]);

        $branch->update($validated);

        return redirect()->route('admin.batches.index')->with('success', 'Branch updated successfully.');
    }

    public function destroyBranch(Branch $branch): RedirectResponse
    {
        if ($branch->batches()->exists()) {
            return back()->with('error', 'Move or delete this branch’s batches before deleting the branch.');
        }

        $branch->delete();

        return back()->with('success', 'Branch deleted successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($queryBuilder) => $queryBuilder->where('is_active', true))],
            'course_id' => ['required', 'exists:courses,id'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where(fn ($queryBuilder) => $queryBuilder->where('role', 'teacher'))],
            'title' => ['required', 'string', 'max:160'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'fee' => ['required', 'numeric', 'min:0'],
        ]);

        Batch::create([...$validated, 'status' => 'open']);

        return redirect()->route('admin.batches.index')->with('success', 'Batch created successfully.');
    }

    public function update(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($queryBuilder) => $queryBuilder->where('is_active', true))],
            'course_id' => ['required', 'exists:courses,id'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where(fn ($queryBuilder) => $queryBuilder->where('role', 'teacher'))],
            'title' => ['required', 'string', 'max:160'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'fee' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:open,closed,completed'],
        ]);

        $batch->update($validated);

        return redirect()->route('admin.batches.index')->with('success', 'Batch updated successfully.');
    }

    public function destroy(Batch $batch): RedirectResponse
    {
        $batch->delete();

        return back()->with('success', 'Batch deleted successfully.');
    }
}