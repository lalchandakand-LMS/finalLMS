<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DeviceSession;
use App\Models\StudentDevice;
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

        return view('admin.users.index', compact('users', 'branches'));
    }

    public function updateRole(User $user, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,branch_manager,accountant,teacher,student,guardian'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('is_active', true)],
            'phone_number' => ['nullable', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/', Rule::unique('users', 'phone_number')->ignore($user->id)],
            'admin_permissions_present' => ['sometimes', 'accepted'],
            'admin_permissions' => ['sometimes', 'array'],
            'admin_permissions.*' => ['string', Rule::in(['branch.batches', 'branch.students', 'finance.invoices', 'finance.refunds', 'finance.reports', 'finance.expenses'])],
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

        $previousRole = $user->role;
        $previousPermissions = $user->admin_permissions ?? [];
        $phoneChanged = array_key_exists('phone_number', $validated) && $user->phone_number !== $validated['phone_number'];
        $roleChanged = $user->role !== $validated['role'];
        $newBranchId = in_array($validated['role'], ['branch_manager', 'accountant', 'student'], true)
            ? ($validated['branch_id'] ?? null)
            : null;
        $branchChanged = (string) $user->branch_id !== (string) $newBranchId;

        $user->update([
            'role' => $validated['role'],
            'branch_id' => $newBranchId,
            'phone_number' => $validated['phone_number'] ?? null,
        ]);

        if ($request->boolean('admin_permissions_present') || $roleChanged) {
            $allowedByRole = [
                'branch_manager' => ['branch.batches', 'branch.students'],
                'accountant' => ['finance.invoices', 'finance.refunds', 'finance.reports', 'finance.expenses'],
            ];
            $requestedPermissions = $request->boolean('admin_permissions_present')
                ? ($validated['admin_permissions'] ?? [])
                : [];
            $user->forceFill([
                'admin_permissions' => array_values(array_intersect(
                    $requestedPermissions,
                    $allowedByRole[$validated['role']] ?? []
                )),
            ])->save();
        }

        $currentPermissions = $user->fresh()->admin_permissions ?? [];
        if ($previousRole !== $validated['role'] || $previousPermissions !== $currentPermissions) {
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'event' => 'admin.user_access.updated',
                'description' => substr(
                    'Updated access for user #'.$user->id.' ('.$previousRole.' to '.$validated['role'].'). Modules: '.implode(', ', $currentPermissions),
                    0,
                    255
                ),
                'method' => strtoupper($request->method()),
                'route' => substr($request->route()?->getName() ?? $request->path(), 0, 190),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        }

        if ($phoneChanged) {
            $user->forceFill([
                'phone_verified_at' => null,
                'phone_verification_code' => null,
                'phone_verification_expires_at' => null,
            ])->save();
        }

        // A device approval is bound to the account's identity and branch access.
        // Revoke trusted devices and every active web session whenever any of those
        // security-sensitive attributes changes, including staff role/branch moves.
        if ($phoneChanged || $roleChanged || $branchChanged) {
            StudentDevice::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['approved', 'pending_admin_approval', 'pending_phone_verification'])
                ->update([
                    'status' => 'revoked',
                    'phone_otp_hash' => null,
                    'phone_otp_expires_at' => null,
                    'updated_at' => now(),
                ]);

            DeviceSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoke_reason' => 'Account role, branch, or phone changed by administrator '.Auth::id(),
                    'updated_at' => now(),
                ]);
        }

        return redirect()->route('admin.users.index')->with('success', 'User role and contact details updated successfully.');
    }
}
