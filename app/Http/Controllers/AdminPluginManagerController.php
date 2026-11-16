<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PluginInstallation;
use App\Models\PluginVersionHistory;
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
    public function index(PluginSettingsManager $settings): View
    {
        $plugins = PluginInstallation::query()->with(['versionHistory.actor'])->orderBy('plugin_key')->get();

        return view('admin.plugins.index', [
            'plugins' => $plugins,
            'pluginSettings' => $plugins->mapWithKeys(fn (PluginInstallation $plugin): array => [
                $plugin->plugin_key => $settings->keys($plugin),
            ]),
            'maxPackageBytes' => (int) config('plugins.max_package_bytes', 52428800),
        ]);
    }

    public function storeSetting(Request $request, string $key, PluginSettingsManager $settings): RedirectResponse
    {
        $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
        abort_unless(in_array($plugin->status, ['installed', 'active', 'inactive'], true), 404);

        $validated = $request->validate([
            'setting_key' => ['required', 'string', 'max:120', 'regex:/^[a-z][a-z0-9_.-]{0,119}$/D'],
            'setting_value' => ['required', 'string', 'min:1', 'max:4096'],
        ]);

        $settings->set($plugin, $validated['setting_key'], $validated['setting_value']);
        $this->audit($request, 'admin.plugins.setting_saved', 'Saved encrypted setting '.$validated['setting_key'].' for '.$plugin->plugin_key);

        return back()->with('success', __('Plugin setting saved encrypted. Its value is never displayed in the manager.'));
    }

    public function deleteSetting(Request $request, string $key, string $settingKey, PluginSettingsManager $settings): RedirectResponse
    {
        $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
        abort_unless($plugin->status !== 'uninstalled', 404);
        abort_unless(preg_match('/^[a-z][a-z0-9_.-]{0,119}$/D', $settingKey) === 1, 404);

        $settings->forget($plugin, $settingKey);
        $this->audit($request, 'admin.plugins.setting_deleted', 'Deleted encrypted setting '.$settingKey.' for '.$plugin->plugin_key);

        return back()->with('success', __('Plugin setting deleted.'));
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

    public function updatePackage(
        Request $request,
        string $key,
        PluginUploadInstaller $installer,
        PluginIntegrityVerifier $integrityVerifier,
        PluginVersionHistoryRecorder $history,
    ): RedirectResponse {
        $validated = $request->validate([
            'package' => ['required', 'file', 'max:51200'],
            'manifest' => ['required', 'string', 'max:32768'],
            'signature' => ['required', 'string', 'max:512'],
            'key_id' => ['required', 'string', 'max:120'],
            'confirm_update' => ['required', 'accepted'],
        ]);

        try {
            $plugin = $installer->update(
                $request->file('package'),
                $validated['manifest'],
                $validated['signature'],
                $validated['key_id'],
                $key,
                $integrityVerifier,
                $history,
            );
        } catch (InvalidArgumentException $exception) {
            $this->recordFailure($request, 'admin.plugins.update_failed');
            return back()->withInput($request->except('package'))->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.update_failed');
            return back()->with('error', __('Plugin update failed safely. The previous package was retained; run a health check and review the activity log.'));
        }

        $history->record($plugin, 'updated', $request->user(), [
            'previous_version' => PluginVersionHistory::query()
                ->where('plugin_key', $plugin->plugin_key)
                ->where('action', 'package_update_snapshot')
                ->latest('created_at')
                ->value('version'),
        ]);
        $this->audit($request, 'admin.plugins.updated', 'Updated signed plugin '.$plugin->plugin_key.' to version '.$plugin->version);

        return redirect()->route('admin.plugins.index')->with('success',
            __('Signed plugin package updated. The previous version was retained; review health and permissions before activation.'));
    }

    public function rollbackPackage(
        Request $request,
        string $key,
        PluginIntegrityVerifier $integrityVerifier,
        PluginSignatureVerifier $signatureVerifier,
        PluginMigrationRunner $migrationRunner,
        PluginLifecycle $lifecycle,
        PluginVersionHistoryRecorder $history,
    ): RedirectResponse {
        $request->validate([
            'confirm_rollback' => ['required', 'accepted'],
        ]);

        $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
        if (! in_array($plugin->status, ['installed', 'inactive'], true)) {
            return back()->with('error', __('Deactivate the plugin and resolve failed states before rolling back its package version.'));
        }

        $snapshotRecord = PluginVersionHistory::query()
            ->where('plugin_key', $plugin->plugin_key)
            ->where('action', 'package_update_snapshot')
            ->where('version', '!=', $plugin->version)
            ->latest('created_at')
            ->first();

        $details = $snapshotRecord?->details;
        $snapshot = is_array($details) && ($details['snapshot_schema'] ?? null) === 1
            ? ($details['snapshot'] ?? null)
            : null;

        if (! is_array($snapshot) || ! is_string($snapshot['version'] ?? null)
            || hash_equals($snapshot['version'], (string) $plugin->version)) {
            return back()->with('error', __('No verified previous package version is available for rollback.'));
        }

        // Preserve the package being rolled back so a later recovery can move forward again.
        PluginVersionHistory::query()->create([
            'plugin_key' => $plugin->plugin_key,
            'version' => $plugin->version,
            'package_sha256' => $plugin->package_sha256,
            'signature_key_id' => $plugin->signature_key_id,
            'action' => 'package_update_snapshot',
            'status' => $plugin->status,
            'actor_id' => $request->user()->id,
            'details' => [
                'snapshot_schema' => 1,
                'snapshot' => [
                    'name' => $plugin->name,
                    'version' => $plugin->version,
                    'type' => $plugin->type,
                    'manifest' => $plugin->manifest,
                    'package_path' => $plugin->package_path,
                    'package_sha256' => $plugin->package_sha256,
                    'signature_key_id' => $plugin->signature_key_id,
                    'signature_verified_at' => $plugin->signature_verified_at?->toISOString(),
                    'metadata' => $plugin->metadata,
                    'installed_at' => $plugin->installed_at?->toISOString(),
                    'health_status' => $plugin->health_status,
                    'health_message' => $plugin->health_message,
                    'last_health_check_at' => $plugin->last_health_check_at?->toISOString(),
                ],
            ],
            'created_at' => now(),
        ]);

        try {
            $candidate = clone $plugin;
            $candidate->forceFill([
                'name' => $snapshot['name'],
                'version' => $snapshot['version'],
                'type' => $snapshot['type'],
                'manifest' => $snapshot['manifest'],
                'package_path' => $snapshot['package_path'],
                'package_sha256' => $snapshot['package_sha256'],
                'signature_key_id' => $snapshot['signature_key_id'],
                'signature_verified_at' => $snapshot['signature_verified_at'],
                'metadata' => $snapshot['metadata'],
                'status' => 'inactive',
            ]);

            // Verify both the retained old package and the currently installed package
            // before reversing only the current version's recorded migrations.
            $signatureVerifier->verifyInstalledSignature($candidate);
            $integrityVerifier->verify($candidate);
            $signatureVerifier->verifyInstalledSignature($plugin);
            $integrityVerifier->verify($plugin);
            $migrationRunner->rollback($plugin);

            $lifecycle->transition($plugin->plugin_key, 'update_staged');
            $plugin->forceFill([
                'name' => $snapshot['name'],
                'version' => $snapshot['version'],
                'type' => $snapshot['type'],
                'manifest' => $snapshot['manifest'],
                'package_path' => $snapshot['package_path'],
                'package_sha256' => $snapshot['package_sha256'],
                'signature_key_id' => $snapshot['signature_key_id'],
                'signature_verified_at' => $snapshot['signature_verified_at'],
                'metadata' => $snapshot['metadata'],
                'installed_at' => $snapshot['installed_at'],
                'activated_at' => null,
            ])->save();

            $plugin = $lifecycle->transition($plugin->plugin_key, 'inactive', [
                'health_status' => 'healthy',
                'health_message' => 'Previous signed package restored after signature and installed-file verification.',
                'last_health_check_at' => now(),
            ]);

            $history->record($plugin, 'package_rolled_back', $request->user(), [
                'from_version' => $snapshotRecord->version,
                'to_version' => $plugin->version,
            ]);
            $this->audit($request, 'admin.plugins.package_rolled_back',
                'Rolled back plugin '.$plugin->plugin_key.' to retained version '.$plugin->version);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.package_rollback_failed');
            return back()->with('error', __('Package rollback failed. The plugin was not reactivated; inspect migration and integrity logs before retrying.'));
        }

        return redirect()->route('admin.plugins.index')->with('success',
            __('Previous signed package restored. The plugin remains inactive until an administrator activates it.'));
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


    public function uninstall(Request $request, string $key, PluginLifecycle $lifecycle, PluginPackageRemover $remover, PluginVersionHistoryRecorder $history): RedirectResponse
    {
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
