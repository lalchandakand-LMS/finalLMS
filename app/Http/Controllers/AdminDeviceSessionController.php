<?php

namespace App\Http\Controllers;

use App\Models\DeviceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDeviceSessionController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = DeviceSession::with(['user.branch', 'trustedDevice'])
            ->when($request->filled('role'), fn ($query) => $query->whereHas('user', fn ($user) => $user->where('role', $request->string('role'))))
            ->when($request->filled('status') && $request->string('status') === 'active', fn ($query) => $query->whereNull('revoked_at')->where('last_seen_at', '>=', now()->subMinutes(config('session.lifetime', 120))))
            ->when($request->filled('status') && $request->string('status') === 'revoked', fn ($query) => $query->whereNotNull('revoked_at'))
            ->when($request->filled('status') && $request->string('status') === 'expired', fn ($query) => $query->whereNull('revoked_at')->where('last_seen_at', '<', now()->subMinutes(config('session.lifetime', 120))))
            ->latest('last_seen_at')
            ->paginate(30)->withQueryString();

        $counts = [
            'active' => DeviceSession::whereNull('revoked_at')->where('last_seen_at', '>=', now()->subMinutes(config('session.lifetime', 120)))->count(),
            'revoked' => DeviceSession::whereNotNull('revoked_at')->count(),
            'expired' => DeviceSession::whereNull('revoked_at')->where('last_seen_at', '<', now()->subMinutes(config('session.lifetime', 120)))->count(),
            'devices' => \App\Models\StudentDevice::count(),
        ];

        return view('admin.security.device-sessions', compact('sessions', 'counts'));
    }

    public function revoke(DeviceSession $deviceSession, Request $request): RedirectResponse
    {
        if ($deviceSession->revoked_at) {
            return back()->with('info', __('This session is already signed out.'));
        }

        $deviceSession->forceFill([
            'revoked_at' => now(),
            'revoke_reason' => 'Signed out by administrator '.$request->user()->id,
        ])->save();

        return back()->with('success', __('The selected device session has been signed out. It will be blocked on its next request.'));
    }

    public function revokeAllForUser(Request $request, int $user): RedirectResponse
    {
        $data = $request->validate(['except_current' => ['nullable', 'boolean']]);
        $query = DeviceSession::where('user_id', $user)->whereNull('revoked_at');

        if (! empty($data['except_current'])) {
            $query->where('session_hash', '!=', hash('sha256', $request->session()->getId()));
        }

        $count = $query->update([
            'revoked_at' => now(),
            'revoke_reason' => 'All sessions signed out by administrator '.$request->user()->id,
            'updated_at' => now(),
        ]);

        return back()->with('success', __('Signed out :count device session(s).', ['count' => $count]));
    }
}
