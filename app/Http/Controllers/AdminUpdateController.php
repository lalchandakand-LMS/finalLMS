<?php

namespace App\Http\Controllers;

use App\Models\SystemUpdateBackup;
use App\Models\SystemUpdateCheck;
use App\Models\SystemUpdateEvent;
use App\Models\SystemUpdateLock;
use App\Models\SystemUpdatePreparation;
use App\Models\SystemUpdatePreflight;
use App\Services\ReleaseActivationPreflight;
use App\Services\ReleaseManifestChecker;
use App\Services\UpdateBackupCoordinator;
use App\Services\ReleasePackageStager;
use App\Services\UpdateOperationLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AdminUpdateController extends Controller
{
    public function index(): View
    {
        $latestPreparation = SystemUpdatePreparation::query()->latest()->first();

        return view('admin.updates.index', [
            'checks' => SystemUpdateCheck::query()
                ->with('checkedBy:id,name')
                ->latest('checked_at')
                ->limit(10)
                ->get(),
            'currentVersion' => (string) config('services.lms_updates.current_version', '0.0.0'),
            'updatesEnabled' => (bool) config('services.lms_updates.enabled', false),
            'preparation' => $latestPreparation ? $latestPreparation->load('preparedBy:id,name') : null,
            'backup' => $latestPreparation
                ? SystemUpdateBackup::query()->where('preparation_id', $latestPreparation->id)->latest()->first()
                : null,
            'preflight' => $latestPreparation
                ? SystemUpdatePreflight::query()->where('preparation_id', $latestPreparation->id)->latest('checked_at')->first()
                : null,
            'operationLock' => SystemUpdateLock::query()->find(1),
            'events' => SystemUpdateEvent::query()
                ->with(['performedBy:id,name', 'preparation:id,version'])
                ->latest()
                ->limit(30)
                ->get(),
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

        $this->recordEvent(
            'release_check',
            $result['status'] === 'failed' ? 'failed' : 'success',
            $result['message'],
            null,
            array_filter([
                'current_version' => $result['current_version'],
                'target_version' => $result['latest_version'],
                'git_commit' => $result['git_commit'],
            ], fn ($value) => $value !== null)
        );

        return redirect()->route('admin.updates.index')
            ->with($result['status'] === 'failed' ? 'error' : 'success', $result['message']);
    }

    public function prepare(ReleasePackageStager $stager, UpdateOperationLock $operationLock): RedirectResponse
    {
        try {
            $preparation = $operationLock->run('package_prepare', auth()->user(), function () use ($stager) {
                $preparation = $stager->prepare(auth()->user());
                $this->recordEvent('package_prepare', 'success', $preparation->message, $preparation, [
                    'version' => $preparation->version,
                    'file_size' => $preparation->file_size,
                ]);

                return $preparation;
            });

            return redirect()->route('admin.updates.index')->with('success', $preparation->message);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The release package could not be prepared. Review the server log; no application files were changed.';
            $this->recordEvent('package_prepare', 'failed', $message);

            return redirect()->route('admin.updates.index')->with('error', $message);
        }
    }

    public function extract(SystemUpdatePreparation $preparation, ReleasePackageStager $stager, UpdateOperationLock $operationLock): RedirectResponse
    {
        try {
            $preparation = $operationLock->run('private_extract', auth()->user(), function () use ($stager, $preparation) {
                $preparation = $stager->extract($preparation);
                $this->recordEvent('private_extract', 'success', $preparation->message, $preparation, [
                    'version' => $preparation->version,
                    'release_path' => $preparation->release_path,
                ]);

                return $preparation;
            });

            return redirect()->route('admin.updates.index')->with('success', $preparation->message);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The release could not be extracted safely. No live application files were changed.';
            $this->recordEvent('private_extract', 'failed', $message, $preparation, [
                'version' => $preparation->version,
            ]);

            return redirect()->route('admin.updates.index')->with('error', $message);
        }
    }

    public function preflight(SystemUpdatePreparation $preparation, ReleaseActivationPreflight $preflight, UpdateOperationLock $operationLock): RedirectResponse
    {
        try {
            $report = $operationLock->run('activation_preflight', auth()->user(), function () use ($preflight, $preparation) {
                $report = $preflight->run($preparation, auth()->user());
                $status = $report->status === 'blocked' ? 'blocked' : ($report->status === 'warning' ? 'warning' : 'success');
                $this->recordEvent('activation_preflight', $status, $report->summary, $preparation, [
                    'preflight_id' => $report->id,
                    'checks' => collect($report->checks)->map(fn (array $check) => [
                        'key' => $check['key'] ?? 'unknown',
                        'status' => $check['status'] ?? 'unknown',
                    ])->all(),
                ]);

                return $report;
            });

            return redirect()->route('admin.updates.index')
                ->with($report->status === 'blocked' ? 'error' : 'success', $report->summary);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The activation preflight could not be completed. No application files or database records were changed.';
            $this->recordEvent('activation_preflight', 'failed', $message, $preparation);

            return redirect()->route('admin.updates.index')->with('error', $message);
        }
    }

    public function createBackup(SystemUpdatePreparation $preparation, UpdateBackupCoordinator $coordinator, UpdateOperationLock $operationLock): RedirectResponse
    {
        try {
            $backup = $operationLock->run('pre_activation_backup', auth()->user(), function () use ($coordinator, $preparation) {
                $backup = $coordinator->create($preparation, auth()->user());
                $this->recordEvent('pre_activation_backup', 'success', $backup->message, $preparation, [
                    'backup_id' => $backup->id,
                    'code_snapshot_sha256' => $backup->code_snapshot_sha256,
                    'database_backup_sha256' => $backup->database_backup_sha256,
                ]);

                return $backup;
            });

            return redirect()->route('admin.updates.index')->with('success', $backup->message);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The pre-activation backup could not be verified. No application files were activated.';
            $this->recordEvent('pre_activation_backup', 'failed', $message, $preparation);

            return redirect()->route('admin.updates.index')->with('error', $message);
        }
    }

    private function recordEvent(
        string $event,
        string $status,
        string $message,
        ?SystemUpdatePreparation $preparation = null,
        array $context = []
    ): void {
        SystemUpdateEvent::create([
            'preparation_id' => $preparation?->id,
            'performed_by' => auth()->id(),
            'event' => $event,
            'status' => $status,
            'message' => $message,
            'context' => $context === [] ? null : $context,
        ]);
    }
}
