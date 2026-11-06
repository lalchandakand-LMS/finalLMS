<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::withCount(['courses', 'enrollments', 'certificates'])
            ->latest()
            ->get();

        return view('admin.users.index', compact('users'));
    }

    public function updateRole(User $user, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,teacher,student'],
        ]);

        if (
            $user->role === 'admin' &&
            $validated['role'] !== 'admin' &&
            User::where('role', 'admin')->count() <= 1
        ) {
            return back()->with('error', 'The last administrator cannot be demoted.');
        }

        $user->update([
            'role' => $validated['role'],
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User role updated successfully.');
    }
}
