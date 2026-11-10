<?php

namespace App\Http\Controllers;

use App\Models\SystemUpdateCheck;
use App\Services\ReleaseManifestChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AdminUpdateController extends Controller
{
    public function index(): View
    {
        $checks = SystemUpdateCheck::query()
            ->with('checkedBy:id,name')
            ->latest('checked_at')
            ->limit(10)
            ->get();

        return view('admin.updates.index', [
            'checks' => $checks,
            'currentVersion' => (string) config('services.lms_updates.current_version', '0.0.0'),
            'updatesEnabled' => (bool) config('services.lms_updates.enabled', false),
        ]);
    }

    public function check(ReleaseManifestChecker $checker): RedirectResponse
    {
        try {
            $result = $checker->check();
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            $result = [
                'status' => 'failed',
                'available' => false,
                'current_version' => (string) config('services.lms_updates.current_version', '0.0.0'),
                'latest_version' => null,
                'manifest_sha256' => null,
                'git_commit' => null,
                'message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'The release check failed unexpectedly. Review the server log; no package was downloaded or installed.',
                'details' => null,
            ];
        }

        SystemUpdateCheck::create([
            'checked_by' => auth()->id(),
            'status' => $result['status'],
            'current_version' => $result['current_version'],
            'target_version' => $result['latest_version'],
            'manifest_sha256' => $result['manifest_sha256'],
            'git_commit' => $result['git_commit'],
            'message' => $result['message'],
            'details' => $result['details'],
            'checked_at' => now(),
        ]);

        return redirect()->route('admin.updates.index')
            ->with($result['status'] === 'failed' ? 'error' : 'success', $result['message']);
    }
}
