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
        $cutoff = now()->subMinutes((int) config('session.lifetime', 120));
        $sessions = DeviceSession::query()
            ->with(['user.branch', 'trustedDevice'])
            ->when($request->filled('role'), fn ($query) => $query->whereHas('user', fn ($user) => $user->where('role', $request->string('role')->toString())))
            ->when($request->input('status') === 'active', fn ($query) => $query->whereNull('revoked_at')->where('last_seen_at', '>=', $cutoff))
            ->when($request->input('status') === 'revoked', fn ($query) => $query->whereNotNull('revoked_at'))
            ->when($request->input('status') === 'expired', fn ($query) => $query->whereNull('revoked_at')->where('last_seen_at', '<', $cutoff))
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
        abort_unless($deviceSession->user_id !== $request->user()->id
            || ! hash_equals($deviceSession->session_hash, hash('sha256', $request->session()->getId())), 422,
            __('You cannot revoke the session currently being used to administer the LMS.'));

        if ($deviceSession->revoked_at) {
            return back()->with('info', __('This session is already signed out.'));
        }

        $deviceSession->forceFill([
            'revoked_at' => now(),
            'revoke_reason' => 'Signed out by administrator '.$request->user()->id,
        ])->save();

        return back()->with('success', __('The selected session has been revoked. It will be blocked on its next request.'));
    }
}
