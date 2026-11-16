<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PluginInstallation;
use App\Services\Plugins\PluginIntegrityVerifier;
use App\Services\Plugins\PluginLifecycle;
use App\Services\Plugins\PluginManifestValidator;
use App\Services\Plugins\PluginMigrationRunner;
use App\Services\Plugins\PluginPackageRemover;
use App\Services\Plugins\PluginUploadInstaller;
use App\Services\Plugins\PluginSignatureVerifier;
use App\Services\Plugins\PluginVersionHistoryRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

class AdminPluginManagerController extends Controller
{
    public function index(): View
    {
        return view('admin.plugins.index', [
            'plugins' => PluginInstallation::query()->with(['versionHistory.actor'])->orderBy('plugin_key')->get(),
            'maxPackageBytes' => (int) config('plugins.max_package_bytes', 52428800),
        ]);
    }

    public function updatePermissions(Request $request, string $key): RedirectResponse
    {
        $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
        abort_unless($plugin->status !== 'uninstalled', 404);

        $validated = $request->validate([
            'permissions' => ['sometimes', 'array', 'max:100'],
            'permissions.*' => ['required', 'string', 'max:160', 'distinct'],
        ]);

        $declared = $plugin->manifest['permissions'] ?? [];
        $requested = array_values(array_unique($validated['permissions'] ?? []));
        if (array_diff($requested, $declared) !== []) {
            return back()->with('error', __('Only permissions declared in the signed plugin manifest can be granted.'));
        }

        $metadata = $plugin->metadata ?? [];
        $metadata['granted_permissions'] = $requested;
        $plugin->forceFill(['metadata' => $metadata])->save();

        $this->audit($request, 'admin.plugins.permissions_updated',
            'Updated granted permissions for '.$plugin->plugin_key.': '.implode(', ', $requested));

        return redirect()->route('admin.plugins.index')->with('success',
            __('Plugin permissions saved. Unchecked permissions are denied immediately.'));
    }

    public function install(Request $request, PluginUploadInstaller $installer, PluginVersionHistoryRecorder $history): RedirectResponse
    {
        $validated = $request->validate([
            'package' => ['required', 'file', 'max:51200'],
            'manifest' => ['required', 'string', 'max:32768'],
            'signature' => ['required', 'string', 'max:512'],
            'key_id' => ['required', 'string', 'max:120'],
        ]);

        try {
            $plugin = $installer->install(
                $request->file('package'),
                $validated['manifest'],
                $validated['signature'],
                $validated['key_id'],
            );
        } catch (InvalidArgumentException $exception) {
            $this->recordFailure($request);
            return back()->withInput($request->except('package'))->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request);
            return back()->withInput($request->except('package'))->with('error',
                __('Plugin installation failed. The package was not activated; check the manifest, signature, trusted key and server logs.'));
        }

        $history->record($plugin, 'installed', $request->user());
        $this->audit($request, 'admin.plugins.installed', 'Installed signed plugin '.$plugin->plugin_key.' version '.$plugin->version);

        return redirect()->route('admin.plugins.index')->with('success',
            __('Plugin package verified and installed. Review its permissions before activation.'));
    }


    public function updateVersion(
        Request $request,
        string $key,
        PluginUploadInstaller $installer,
        PluginVersionHistoryRecorder $history,
    ): RedirectResponse {
        $validated = $request->validate([
            'package' => ['required', 'file', 'max:51200'],
            'manifest' => ['required', 'string', 'max:32768'],
            'signature' => ['required', 'string', 'max:512'],
            'key_id' => ['required', 'string', 'max:120'],
        ]);

        try {
            $current = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
            $manifest = json_decode($validated['manifest'], true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($manifest) || ($manifest['key'] ?? null) !== $key) {
                throw new RuntimeException('The update manifest plugin key does not match the selected plugin.');
            }
            $updated = $installer->update(
                $request->file('package'),
                $validated['manifest'],
                $validated['signature'],
                $validated['key_id'],
            );
            $history->record($updated, 'version_updated', $request->user(), [
                'from_version' => $current->version,
                'to_version' => $updated->version,
            ]);
            $this->audit($request, 'admin.plugins.version_updated',
                'Updated plugin '.$key.' from '.$current->version.' to '.$updated->version);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.version_update_failed');
            return back()->with('error', __('Plugin update failed or was blocked. The previous signed package was retained; inspect the activity log and database state before retrying.'));
        }

        return redirect()->route('admin.plugins.index')->with('success',
            __('The new signed plugin version was installed. It remains inactive until health checks and explicit activation.'));
    }

    public function restoreVersion(
        Request $request,
        string $key,
        int $snapshotId,
        PluginVersionRestorer $restorer,
    ): RedirectResponse {
        $request->validate(['confirm_restore' => ['required', 'accepted']]);

        try {
            $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
            $snapshot = PluginVersionHistory::query()
                ->whereKey($snapshotId)
                ->where('plugin_key', $key)
                ->where('action', 'version_snapshot')
                ->firstOrFail();
            $restored = $restorer->restore($plugin, $snapshot, $request->user());
            $this->audit($request, 'admin.plugins.version_restored',
                'Restored plugin '.$key.' to version '.$restored->version);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.version_restore_failed');
            return back()->with('error', __('Version restore was blocked or failed. The current version was not switched unless the verified migration rollback completed; inspect server logs and database state.'));
        }

        return redirect()->route('admin.plugins.index')->with('success',
            __('The previous signed version was restored and left inactive. Run a health check before activation.'));
    }

    public function activate(
        Request $request,
        string $key,
        PluginManifestValidator $manifestValidator,
        PluginIntegrityVerifier $integrityVerifier,
        PluginSignatureVerifier $signatureVerifier,
        PluginMigrationRunner $migrationRunner,
        PluginLifecycle $lifecycle,
        PluginVersionHistoryRecorder $history,
    ): RedirectResponse {
        if (app()->routesAreCached()) {
            return back()->with('error', __('Clear the Laravel route cache before activating plugins. Plugin routes cannot be safely registered from a cached route file.'));
        }

        try {
            $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
            if (! in_array($plugin->status, ['installed', 'inactive'], true)) {
                throw new LogicException('Only an installed or inactive plugin can be activated.');
            }

            $signatureVerifier->verifyInstalledSignature($plugin);

            $errors = $manifestValidator->errors($plugin->manifest ?? []);
            if ($errors !== []) {
                throw new RuntimeException('The plugin manifest is invalid: '.implode(' ', $errors));
            }

            if (! hash_equals((string) $plugin->plugin_key, (string) $plugin->manifest['key'])
                || (string) $plugin->name !== (string) $plugin->manifest['name']
                || (string) $plugin->version !== (string) $plugin->manifest['version']
                || (string) $plugin->type !== (string) $plugin->manifest['type']) {
                throw new RuntimeException('The plugin manifest identity does not match its installed registry record.');
            }

            $this->assertDependenciesAvailable($plugin);
            $integrityVerifier->verify($plugin);
            // Run only migrations from the already signature-verified, integrity-checked package.
            // A migration failure leaves the plugin installed but inactive.
            $migrationRunner->migrate($plugin);
            $lifecycle->transition($plugin->plugin_key, 'active');
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.activation_failed');
            return back()->with('error', __('Plugin activation was blocked by a security or dependency check. Review the plugin manifest, dependencies, trusted release key and server logs.'));
        }

        $history->record($plugin, 'activated', $request->user());
        $this->audit($request, 'admin.plugins.activated', 'Activated signed plugin '.$plugin->plugin_key.' version '.$plugin->version);

        return redirect()->route('admin.plugins.index')->with('success',
            __('Plugin activated. Its verified runtime will register on the next request.'));
    }

    public function deactivate(Request $request, string $key, PluginLifecycle $lifecycle, PluginVersionHistoryRecorder $history): RedirectResponse
    {
        try {
            $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
            if ($plugin->status !== 'active') {
                throw new LogicException('Only an active plugin can be deactivated.');
            }

            $dependent = PluginInstallation::query()->where('status', 'active')
                ->where('plugin_key', '!=', $plugin->plugin_key)
                ->get()
                ->first(fn (PluginInstallation $candidate): bool =>
                    in_array($plugin->plugin_key, $candidate->manifest['dependencies'] ?? [], true)
                );

            if ($dependent) {
                throw new LogicException('An active plugin depends on this plugin: '.$dependent->plugin_key);
            }

            $lifecycle->transition($plugin->plugin_key, 'inactive');
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', __('Plugin could not be deactivated. It may have active dependants; no registry state was changed.'));
        }

        $history->record($plugin, 'deactivated', $request->user());
        $this->audit($request, 'admin.plugins.deactivated',
            'Deactivated plugin '.$plugin->plugin_key.'; installed files and data were retained.');

        return redirect()->route('admin.plugins.index')->with('success',
            __('Plugin deactivated. Its installed files and data were retained.'));
    }


    public function rollbackMigrations(
        Request $request,
        string $key,
        PluginSignatureVerifier $signatureVerifier,
        PluginManifestValidator $manifestValidator,
        PluginIntegrityVerifier $integrityVerifier,
        PluginMigrationRunner $migrationRunner,
        PluginVersionHistoryRecorder $history,
    ): RedirectResponse {
        $request->validate(['confirm_rollback' => ['required', 'accepted']]);

        try {
            $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
            if (! in_array($plugin->status, ['installed', 'inactive'], true)) {
                throw new LogicException('Deactivate the plugin and ensure it is installed before rolling back migrations.');
            }

            $signatureVerifier->verifyInstalledSignature($plugin);
            $errors = $manifestValidator->errors($plugin->manifest ?? []);
            if ($errors !== []) {
                throw new RuntimeException('The plugin manifest is invalid: '.implode(' ', $errors));
            }
            if (! hash_equals((string) $plugin->plugin_key, (string) $plugin->manifest['key'])
                || (string) $plugin->name !== (string) $plugin->manifest['name']
                || (string) $plugin->version !== (string) $plugin->manifest['version']
                || (string) $plugin->type !== (string) $plugin->manifest['type']) {
                throw new RuntimeException('The plugin manifest identity does not match its installed registry record.');
            }

            $integrityVerifier->verify($plugin);
            $migrationRunner->rollback($plugin);
            $history->record($plugin, 'migrations_rolled_back', $request->user());
            $this->audit($request, 'admin.plugins.migrations_rolled_back',
                'Rolled back completed migrations for plugin '.$plugin->plugin_key.' version '.$plugin->version);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.migration_rollback_failed');
            return back()->with('error', __('Migration rollback was blocked or failed. The plugin package and registry were retained; review server logs and database state.'));
        }

        return redirect()->route('admin.plugins.index')->with('success',
            __('Completed migrations for this plugin version were rolled back. The plugin remains installed and inactive.'));
    }

    public function uninstall(Request $request, string $key, PluginLifecycle $lifecycle, PluginPackageRemover $remover, PluginVersionHistoryRecorder $history): RedirectResponse
    {
        $plugin = null;
        try {
            $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
            if (! in_array($plugin->status, ['installed', 'inactive', 'failed'], true)) {
                throw new LogicException('Deactivate the plugin before uninstalling it.');
            }

            $dependent = PluginInstallation::query()->where('status', 'active')
                ->where('plugin_key', '!=', $plugin->plugin_key)
                ->get()
                ->first(fn (PluginInstallation $candidate): bool =>
                    in_array($plugin->plugin_key, $candidate->manifest['dependencies'] ?? [], true)
                );
            if ($dependent) {
                throw new LogicException('An active plugin depends on this plugin: '.$dependent->plugin_key);
            }

            $plugin = $lifecycle->transition($plugin->plugin_key, 'uninstalled');
            $remover->remove($plugin);
            $history->record($plugin, 'uninstalled', $request->user());
        } catch (Throwable $exception) {
            report($exception);
            // If the registry was marked uninstalled but filesystem cleanup failed,
            // retain a retryable failed state. The runtime loads active plugins only.
            if ($plugin instanceof PluginInstallation && $plugin->status === 'uninstalled') {
                try {
                    $lifecycle->transition($plugin->plugin_key, 'failed', [
                        'health_status' => 'failed',
                        'health_message' => 'Plugin package cleanup failed; retry uninstall after reviewing server logs.',
                    ]);
                } catch (Throwable $stateException) {
                    report($stateException);
                }
            }
            $this->recordFailure($request, 'admin.plugins.uninstall_failed');
            return back()->with('error', __('Plugin uninstall was blocked or package cleanup failed. Plugin data and migration records were retained.'));
        }

        $this->audit($request, 'admin.plugins.uninstalled',
            'Uninstalled plugin '.$plugin->plugin_key.'; application data and migration history were retained.');

        return redirect()->route('admin.plugins.index')->with('success',
            __('Plugin uninstalled. Its executable package was removed; plugin data and migration records were retained.'));
    }

    public function healthCheck(
        Request $request,
        string $key,
        PluginManifestValidator $manifestValidator,
        PluginIntegrityVerifier $integrityVerifier,
        PluginSignatureVerifier $signatureVerifier,
        PluginLifecycle $lifecycle,
        PluginVersionHistoryRecorder $history,
    ): RedirectResponse {
        $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
        if (! in_array($plugin->status, ['installed', 'inactive', 'active', 'failed'], true)) {
            return back()->with('error', __('Health checks are available only for installed plugins.'));
        }

        try {
            $signatureReverified = $signatureVerifier->verifyInstalledSignature($plugin);

            $errors = $manifestValidator->errors($plugin->manifest ?? []);
            if ($errors !== []) {
                throw new RuntimeException('The plugin manifest is invalid: '.implode(' ', $errors));
            }

            if (! hash_equals((string) $plugin->plugin_key, (string) $plugin->manifest['key'])
                || (string) $plugin->name !== (string) $plugin->manifest['name']
                || (string) $plugin->version !== (string) $plugin->manifest['version']
                || (string) $plugin->type !== (string) $plugin->manifest['type']) {
                throw new RuntimeException('The plugin manifest identity does not match its installed registry record.');
            }

            $this->assertDependenciesAvailable($plugin);
            $integrityVerifier->verify($plugin);
            $healthAttributes = [
                'health_status' => 'healthy',
                'health_message' => $signatureReverified
                    ? 'Detached release signature, manifest and installed files verified.'
                    : 'Legacy release signature was verified at install; manifest and installed files verified.',
                'last_health_check_at' => now(),
            ];
            if ($plugin->status === 'failed') {
                $plugin = $lifecycle->transition($plugin->plugin_key, 'inactive', $healthAttributes);
            } else {
                $plugin->forceFill($healthAttributes)->save();
                $plugin->refresh();
            }
            $history->record($plugin, 'health_check_passed', $request->user());
            $this->audit($request, 'admin.plugins.health_check_passed',
                'Verified plugin health for '.$plugin->plugin_key.' version '.$plugin->version);

            return redirect()->route('admin.plugins.index')->with('success', __('Plugin health check passed.'));
        } catch (Throwable $exception) {
            report($exception);
            $attributes = [
                'health_status' => 'failed',
                'health_message' => 'A trusted signature, manifest, dependency or installed-file integrity check failed. Plugin execution is blocked when active.',
                'last_health_check_at' => now(),
            ];

            if ($plugin->status === 'active') {
                try {
                    $plugin = $lifecycle->transition($plugin->plugin_key, 'failed', $attributes);
                } catch (Throwable $transitionException) {
                    report($transitionException);
                    $plugin->forceFill($attributes)->save();
                    $plugin->refresh();
                }
            } else {
                $plugin->forceFill($attributes)->save();
                $plugin->refresh();
            }

            $history->record($plugin, 'health_check_failed', $request->user(), [
                'failure_type' => class_basename($exception),
            ]);
            $this->recordFailure($request, 'admin.plugins.health_check_failed');

            return back()->with('error', __('Plugin health check failed. Review the activity log; active plugin execution was blocked.'));
        }
    }

    private function assertDependenciesAvailable(PluginInstallation $plugin): void
    {
        foreach (($plugin->manifest['dependencies'] ?? []) as $dependency) {
            if (! PluginInstallation::query()->where('plugin_key', $dependency)->where('status', 'active')->exists()) {
                throw new RuntimeException('A required plugin dependency is not active: '.$dependency);
            }
        }

        foreach (($plugin->manifest['conflicts'] ?? []) as $conflict) {
            if (PluginInstallation::query()->where('plugin_key', $conflict)->where('status', 'active')->exists()) {
                throw new RuntimeException('A conflicting plugin is active: '.$conflict);
            }
        }
    }

    private function audit(Request $request, string $event, string $description): void
    {
        try {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => $event,
                'description' => $description,
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function recordFailure(Request $request, string $event = 'admin.plugins.install_failed'): void
    {
        try {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => $event,
                'description' => 'A plugin lifecycle operation was rejected or failed validation.',
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
