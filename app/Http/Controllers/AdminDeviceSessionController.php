<?php

namespace App\\Http\\Controllers;

use App\\Models\\DeviceSession;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\View\\View;

class AdminDeviceSessionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'role' => ['nullable', 'in:admin,branch_manager,accountant,teacher,student,guardian'],
            'status' => ['nullable', 'in:active,expired,revoked'],
        ]);

        $cutoff = now()->subMinutes((int) config('session.lifetime', 120));
        $sessions = DeviceSession::query()
            ->with(['user.branch', 'trustedDevice'])
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->whereHas('user', fn ($user) => $user->where('role', $role)))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->whereNull('revoked_at')->where('last_seen_at', '>=', $cutoff))
            ->when(($filters['status'] ?? null) === 'revoked', fn ($query) => $query->whereNotNull('revoked_at'))
            ->when(($filters['status'] ?? null) === 'expired', fn ($query) => $query->whereNull('revoked_at')->where('last_seen_at', '<', $cutoff))
            ->latest('last_seen_at')
            ->paginate(30)
            ->withQueryString();

        $counts = [
            'active' => DeviceSession::whereNull('revoked_at')->where('last_seen_at', '>=', $cutoff)->count(),
            'revoked' => DeviceSession::whereNotNull('revoked_at')->count(),
            'expired' => DeviceSession::whereNull('revoked_at')->where('last_seen_at', '<', $cutoff)->count(),
        ];

        return view('admin.security.device-sessions', compact('sessions', 'counts'));
    }

    public function revoke(DeviceSession $deviceSession, Request $request): RedirectResponse
    {
        $currentSessionHash = $request->session()->get(
            'device_session_hash',
            hash('sha256', $request->session()->getId())
        );

        abort_unless(
            $deviceSession->user_id !== $request->user()->id
                || ! hash_equals($deviceSession->session_hash, $currentSessionHash),
            422,
            __('You cannot revoke the session currently being used to administer the LMS.')
        );

        $revoked = DB::table('device_sessions')
            ->where('id', $deviceSession->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoke_reason' => 'Signed out by administrator '.$request->user()->id,
                'updated_at' => now(),
            ]);

        if ($revoked === 0) {
            return back()->with('info', __('This session is already signed out.'));
        }

        return back()->with('success', __('The selected session has been revoked. It will be blocked on its next request.'));
    }
}
