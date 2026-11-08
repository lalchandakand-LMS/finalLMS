<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    private function roles(): array
    {
        return ['admin', 'branch_manager', 'accountant', 'teacher', 'student'];
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:admin,branch_manager,accountant,teacher,student'],
        ]);
        $users = User::query()->with('branch')->withCount(['courses', 'enrollments', 'certificates'])
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone_number', 'like', '%'.$search.'%');
                });
            })
            ->when($validated['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->latest()->paginate(25)->withQueryString();
        $branches = Branch::query()->orderBy('name')->get();
        return view('admin.users.index', compact('users', 'branches'));
    }

    public function create(): View
    {
        $user = new User();
        $branches = Branch::query()->orderBy('name')->get();
        return view('admin.users.form', compact('user', 'branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in($this->roles())],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'password' => ['required', 'confirmed', Password::min(12)],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $this->validateBranchAssignment($validated['role'], $validated['branch_id'] ?? null);
        $validated['branch_id'] = in_array($validated['role'], ['branch_manager', 'accountant', 'student'], true) ? ($validated['branch_id'] ?? null) : null;
        $photo = $validated['profile_photo'] ?? null;
        unset($validated['profile_photo']);
        $user = User::create($validated);
        $user->forceFill(['email_verified_at' => now()])->save();
        if ($photo) {
            $user->forceFill(['profile_photo_path' => $photo->store('profile-photos', 'public')])->save();
        }
        return redirect()->route('admin.users.index')->with('success', 'Account created successfully.');
    }

    public function edit(User $user): View
    {
        $branches = Branch::query()->orderBy('name')->get();
        return view('admin.users.form', compact('user', 'branches'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in($this->roles())],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'password' => ['nullable', 'confirmed', Password::min(12)],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_photo' => ['nullable', 'boolean'],
        ]);
        $this->validateBranchAssignment($validated['role'], $validated['branch_id'] ?? null);
        if ($user->id === Auth::id() && $validated['role'] !== 'admin') {
            return back()->withInput()->withErrors(['role' => 'You cannot remove your own administrator access.']);
        }
        if ($user->isAdmin() && $validated['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->withInput()->withErrors(['role' => 'The last administrator cannot be demoted.']);
        }
        $validated['branch_id'] = in_array($validated['role'], ['branch_manager', 'accountant', 'student'], true) ? ($validated['branch_id'] ?? null) : null;
        $photo = $validated['profile_photo'] ?? null;
        $removePhoto = (bool) ($validated['remove_profile_photo'] ?? false);
        unset($validated['profile_photo'], $validated['remove_profile_photo']);
        if (empty($validated['password'])) {
            unset($validated['password']);
        }
        $user->update($validated);
        if ($photo) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $user->forceFill(['profile_photo_path' => $photo->store('profile-photos', 'public')])->save();
        } elseif ($removePhoto && $user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->forceFill(['profile_photo_path' => null])->save();
        }
        return redirect()->route('admin.users.index')->with('success', 'Account profile updated successfully.');
    }

    public function trash(): View
    {
        $users = User::onlyTrashed()->with('branch')->latest('deleted_at')->paginate(25);
        return view('admin.users.trash', compact('users'));
    }

    public function restore(int $userId): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($userId);
        $user->restore();
        return redirect()->route('admin.users.trash')->with('success', 'Account restored successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own signed-in account.');
        }
        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'The last administrator cannot be deleted.');
        }
        if ($user->courses()->exists() || $user->enrollments()->exists() || $user->certificates()->exists()) {
            return back()->with('error', 'This account has course, enrollment or certificate records. Reassign those records before deleting the account.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Account moved to trash successfully.');
    }

    public function updateRole(User $user, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in($this->roles())],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_active', true))],
        ]);
        if ($user->id === Auth::id() && $validated['role'] !== 'admin') {
            return back()->with('error', 'You cannot remove your own administrator access.');
        }
        if ($user->isAdmin() && $validated['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'The last administrator cannot be demoted.');
        }
        $this->validateBranchAssignment($validated['role'], $validated['branch_id'] ?? null);
        $user->update([
            'role' => $validated['role'],
            'branch_id' => in_array($validated['role'], ['branch_manager', 'accountant', 'student'], true) ? ($validated['branch_id'] ?? null) : null,
        ]);
        return redirect()->route('admin.users.index')->with('success', 'User role updated successfully.');
    }

    private function validateBranchAssignment(string $role, ?int $branchId): void
    {
        if (in_array($role, ['branch_manager', 'accountant'], true) && !$branchId) {
            throw \Illuminate\Validation\ValidationException::withMessages(['branch_id' => 'Select a branch for this staff account.']);
        }
        if ($branchId && !Branch::whereKey($branchId)->where('is_active', true)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['branch_id' => 'Choose an active branch.']);
        }
    }
}
