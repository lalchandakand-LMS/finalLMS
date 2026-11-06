<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:admin,teacher,student'],
        ]);

        $users = User::query()
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

        return view('admin.users.index', compact('users'));
    }

    public function updateRole(User $user, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,teacher,student'],
        ]);

        if ($user->id === Auth::id() && $validated['role'] !== 'admin') {
            return back()->with('error', 'You cannot remove your own administrator access.');
        }

        if ($user->isAdmin() && $validated['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'The last administrator cannot be demoted.');
        }

        $user->update([
            'role' => $validated['role'],
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User role updated successfully.');
    }
}
