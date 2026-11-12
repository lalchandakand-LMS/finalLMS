<?php

namespace App\Http\Controllers;

use App\Models\SystemUpdateBackup;
use App\Models\SystemUpdateCheck;
use App\Models\SystemUpdatePreparation;
use App\Models\SystemUpdatePreflight;
use App\Services\ReleaseActivationPreflight;
use App\Services\ReleaseManifestChecker;
use App\Services\UpdateBackupCoordinator;
use App\Services\ReleasePackageStager;
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
            'preparation' => SystemUpdatePreparation::query()->with('preparedBy:id,name')->latest()->first(),
            'backup' => SystemUpdateBackup::query()->where('preparation_id', SystemUpdatePreparation::query()->latest()->value('id'))->latest()->first(),
            'preflight' => SystemUpdatePreflight::query()->where('preparation_id', SystemUpdatePreparation::query()->latest()->value('id'))->latest('checked_at')->first(),
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
    public function prepare(ReleasePackageStager $stager): RedirectResponse
    {
        try {
            $preparation = $stager->prepare(auth()->user());

            return redirect()->route('admin.updates.index')->with('success', $preparation->message);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The release package could not be prepared. Review the server log; no application files were changed.';

            return redirect()->route('admin.updates.index')->with('error', $message);
        }
    }

    public function extract(SystemUpdatePreparation $preparation, ReleasePackageStager $stager): RedirectResponse
    {
        try {
            $preparation = $stager->extract($preparation);

            return redirect()->route('admin.updates.index')->with('success', $preparation->message);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The release could not be extracted safely. No live application files were changed.';

            return redirect()->route('admin.updates.index')->with('error', $message);
        }
    }

    public function preflight(SystemUpdatePreparation $preparation, ReleaseActivationPreflight $preflight): RedirectResponse
    {
        try {
            $report = $preflight->run($preparation, auth()->user());

            return redirect()->route('admin.updates.index')
                ->with($report->status === 'blocked' ? 'error' : 'success', $report->summary);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The activation preflight could not be completed. No application files or database records were changed.';

            return redirect()->route('admin.updates.index')->with('error', $message);
        }
    }

    public function createBackup(SystemUpdatePreparation $preparation, UpdateBackupCoordinator $coordinator): RedirectResponse
    {
        try {
            $backup = $coordinator->create($preparation, auth()->user());

            return redirect()->route('admin.updates.index')
                ->with('success', $backup->message);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The pre-activation backup could not be verified. No application files were activated.';

            return redirect()->route('admin.updates.index')->with('error', $message);
        }
    }

}
