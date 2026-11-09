<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:admin,branch_manager,accountant,teacher,student,guardian'],
        ]);

        $users = User::query()
            ->with(['branch'])
            ->withCount(['courses', 'enrollments', 'certificates'])
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone_number', 'like', '%'.$search.'%');
                });
            })
            ->when($validated['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();
        $guardians = User::query()->where('role', 'guardian')->orderBy('name')->get(['id', 'name', 'email']);
        $students = User::query()->where('role', 'student')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.users.index', compact('users', 'branches', 'guardians', 'students'));
    }

    public function linkStudent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'guardian_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'guardian')],
            'student_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')],
        ]);

        \App\Models\GuardianStudent::query()->firstOrCreate(
            ['guardian_id' => $validated['guardian_id'], 'student_id' => $validated['student_id']],
            ['linked_by' => Auth::id()]
        );

        return redirect()->route('admin.users.index')->with('success', 'Guardian and student linked successfully.');
    }

    public function unlinkStudent(User $guardian, User $student): RedirectResponse
    {
        abort_unless($guardian->isGuardian() && $student->isStudent(), 404);

        \App\Models\GuardianStudent::query()
            ->where('guardian_id', $guardian->id)
            ->where('student_id', $student->id)
            ->delete();

        return redirect()->route('admin.users.index')->with('success', 'Guardian link removed.');
    }

    public function updateRole(User $user, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,branch_manager,accountant,teacher,student,guardian'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('is_active', true)],
        ]);

        if ($user->id === Auth::id() && $validated['role'] !== 'admin') {
            return back()->with('error', 'You cannot remove your own administrator access.');
        }

        if ($user->isAdmin() && $validated['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'The last administrator cannot be demoted.');
        }

        if (in_array($validated['role'], ['branch_manager', 'accountant'], true) && empty($validated['branch_id'])) {
            return back()->withErrors(['branch_id' => 'Select an active branch for this staff account.']);
        }

        $user->update([
            'role' => $validated['role'],
            'branch_id' => in_array($validated['role'], ['branch_manager', 'accountant', 'student'], true) ? ($validated['branch_id'] ?? null) : null,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User role updated successfully.');
    }
}
