<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PluginInstallation;
use App\Services\Plugins\PluginCompatibilityChecker;
use App\Services\Plugins\PluginDiscoveryService;
use App\Services\Plugins\PluginHealthChecker;
use App\Services\Plugins\PluginIntegrityVerifier;
use App\Services\Plugins\PluginLifecycle;
use App\Services\Plugins\PluginManifestValidator;
use App\Services\Plugins\PluginMigrationRunner;
use App\Services\Plugins\PluginPackageRemover;
use App\Services\Plugins\PluginUninstallInspector;
use App\Services\Plugins\PluginUpdater;
use App\Services\Plugins\PluginUploadInstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

class AdminPluginManagerController extends Controller
{
    public function index(
        Request $request,
        PluginCompatibilityChecker $compatibilityChecker,
        PluginUninstallInspector $uninstallInspector,
    ): View {
        $statusFilter = $request->string('status')->trim()->toString();
        $portalFilter = $request->string('portal')->trim()->toString();
        $typeFilter = $request->string('type')->trim()->toString();

        $allPlugins = PluginInstallation::query()->orderBy('plugin_key')->get();

        $plugins = $allPlugins->filter(function (PluginInstallation $plugin) use ($statusFilter, $portalFilter, $typeFilter): bool {
            if ($statusFilter !== '' && $plugin->status !== $statusFilter) {
                return false;
            }
            if ($typeFilter !== '' && $plugin->type !== $typeFilter) {
                return false;
            }
            if ($portalFilter !== '' && ! in_array($portalFilter, $plugin->manifest['portals'] ?? [], true)) {
                return false;
            }

            return true;
        })->values();

        $preflights = [];
        $compatibility = [];
        foreach ($plugins as $plugin) {
            $preflights[$plugin->plugin_key] = $uninstallInspector->preflight($plugin);
            $compatibility[$plugin->plugin_key] = $compatibilityChecker->check(is_array($plugin->manifest) ? $plugin->manifest : []);
        }

        $auditLogs = ActivityLog::query()
            ->where('event', 'like', 'admin.plugins.%')
            ->latest('id')
            ->limit(15)
            ->get();

        return view('admin.plugins.index', [
            'plugins' => $plugins,
            'totalRegistered' => $allPlugins->count(),
            'statusFilter' => $statusFilter,
            'portalFilter' => $portalFilter,
            'typeFilter' => $typeFilter,
            'preflights' => $preflights,
            'compatibility' => $compatibility,
            'auditLogs' => $auditLogs,
            'maxPackageBytes' => (int) config('plugins.max_package_bytes', 52428800),
            'environmentVersions' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'lms' => $compatibilityChecker->resolveCurrentLmsVersion(),
            ],
        ]);
    }

    public function discover(Request $request, PluginDiscoveryService $discovery): RedirectResponse
    {
        try {
            $summary = $discovery->discover();
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.discovery_failed');

            return back()->with('error', __('Plugin directory scan failed. Check private plugins directory permissions and server logs.'));
        }

        $this->audit(
            $request,
            'admin.plugins.discovered',
            sprintf(
                'Scanned private plugins directory: %d package(s) checked, %d newly discovered, %d skipped.',
                $summary['scanned'],
                $summary['discovered'],
                count($summary['skipped']),
            ),
        );

        return redirect()->route('admin.plugins.index')->with(
            'success',
            __('Scanned private plugins directory: :discovered newly discovered package(s), :skipped skipped.', [
                'discovered' => $summary['discovered'],
                'skipped' => count($summary['skipped']),
            ]),
        );
    }

    public function install(Request $request, PluginUploadInstaller $installer): RedirectResponse
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

            return back()->withInput($request->except('package'))->with(
                'error',
                __('Plugin installation failed. The package was not activated; check the manifest, signature, trusted key and server logs.'),
            );
        }

        $this->audit($request, 'admin.plugins.installed', 'Installed signed plugin '.$plugin->plugin_key.' version '.$plugin->version);

        return redirect()->route('admin.plugins.index')->with(
            'success',
            __('Plugin package verified and installed. Review its permissions before activation.'),
        );
    }

    public function update(Request $request, string $key, PluginUpdater $updater): RedirectResponse
    {
        if (app()->routesAreCached()) {
            return back()->with('error', __('Clear the Laravel route cache before updating plugins.'));
        }

        $validated = $request->validate([
            'package' => ['required', 'file', 'max:51200'],
            'manifest' => ['required', 'string', 'max:32768'],
            'signature' => ['required', 'string', 'max:512'],
            'key_id' => ['required', 'string', 'max:120'],
        ]);

        try {
            $plugin = $updater->update(
                $key,
                $request->file('package'),
                $validated['manifest'],
                $validated['signature'],
                $validated['key_id'],
            );
        } catch (InvalidArgumentException $exception) {
            $this->recordFailure($request, 'admin.plugins.update_failed');

            return back()->withInput($request->except('package'))->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.update_failed');

            return back()->withInput($request->except('package'))->with(
                'error',
                __('Plugin update failed and was rolled back safely. Check the manifest version, compatibility, signature and server logs.'),
            );
        }

        $this->audit($request, 'admin.plugins.updated', 'Upgraded signed plugin '.$plugin->plugin_key.' to version '.$plugin->version);

        return redirect()->route('admin.plugins.index')->with(
            'success',
            __('Plugin upgraded cleanly to version :version.', ['version' => $plugin->version]),
        );
    }

    public function health(Request $request, string $key, PluginHealthChecker $checker): RedirectResponse
    {
        $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
        $result = $checker->checkAndRecord($plugin);

        $this->audit(
            $request,
            'admin.plugins.health_checked',
            'Health check for '.$plugin->plugin_key.': '.$result['status'].' — '.$result['message'],
        );

        $flashKey = $result['status'] === 'healthy' ? 'success' : 'error';

        return redirect()->route('admin.plugins.index')->with(
            $flashKey,
            __('Plugin health check (:status): :message', [
                'status' => ucfirst($result['status']),
                'message' => $result['message'],
            ]),
        );
    }

    public function export(Request $request, string $key, PluginUninstallInspector $inspector): JsonResponse
    {
        $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
        $payload = $inspector->exportPayload($plugin);

        $this->audit($request, 'admin.plugins.exported', 'Exported sanitized metadata & migration report for '.$plugin->plugin_key);

        $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $plugin->plugin_key).'-report.json';

        return response()
            ->json($payload, 200, [
                'Content-Disposition' => 'attachment; filename="'.$safeFilename.'"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function activate(
        Request $request,
        string $key,
        PluginManifestValidator $manifestValidator,
        PluginCompatibilityChecker $compatibilityChecker,
        PluginIntegrityVerifier $integrityVerifier,
        PluginMigrationRunner $migrationRunner,
        PluginLifecycle $lifecycle,
    ): RedirectResponse {
        if (app()->routesAreCached()) {
            return back()->with('error', __('Clear the Laravel route cache before activating plugins. Plugin routes cannot be safely registered from a cached route file.'));
        }

        try {
            $plugin = PluginInstallation::query()->where('plugin_key', $key)->firstOrFail();
            if (! in_array($plugin->status, ['installed', 'inactive'], true)) {
                throw new LogicException('Only an installed or inactive plugin can be activated.');
            }

            $trustedKeys = config('plugins.trusted_public_keys', []);
            if (! is_array($trustedKeys) || ! is_string($plugin->signature_key_id)
                || ! array_key_exists($plugin->signature_key_id, $trustedKeys)
                || $plugin->signature_verified_at === null) {
                throw new RuntimeException('The plugin release signature is not currently trusted.');
            }

            $errors = $manifestValidator->errors($plugin->manifest ?? []);
            if ($errors !== []) {
                throw new RuntimeException('The plugin manifest is invalid: '.implode(' ', $errors));
            }

            $compatibilityChecker->assertCompatible($plugin->manifest ?? []);

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
            $lifecycle->transition($plugin->plugin_key, 'active', [
                'health_status' => 'healthy',
                'health_message' => 'Activated cleanly after integrity and compatibility verification.',
                'last_health_check_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.activation_failed');

            return back()->with('error', __('Plugin activation was blocked by a security or dependency check. Review the plugin manifest, dependencies, trusted release key and server logs.'));
        }

        $this->audit($request, 'admin.plugins.activated', 'Activated signed plugin '.$plugin->plugin_key.' version '.$plugin->version);

        return redirect()->route('admin.plugins.index')->with(
            'success',
            __('Plugin activated. Its verified runtime will register on the next request.'),
        );
    }

    public function deactivate(Request $request, string $key, PluginLifecycle $lifecycle): RedirectResponse
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

        $this->audit(
            $request,
            'admin.plugins.deactivated',
            'Deactivated plugin '.$plugin->plugin_key.'; installed files and data were retained.',
        );

        return redirect()->route('admin.plugins.index')->with(
            'success',
            __('Plugin deactivated. Its installed files and data were retained.'),
        );
    }

    public function uninstall(Request $request, string $key, PluginLifecycle $lifecycle, PluginPackageRemover $remover): RedirectResponse
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
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.uninstall_failed');

            return back()->with('error', __('Plugin uninstall was blocked or package cleanup failed. Plugin data and migration records were retained.'));
        }

        $this->audit(
            $request,
            'admin.plugins.uninstalled',
            'Uninstalled plugin '.$plugin->plugin_key.'; application data and migration history were retained.',
        );

        return redirect()->route('admin.plugins.index')->with(
            'success',
            __('Plugin uninstalled. Its executable package was removed; plugin data and migration records were retained.'),
        );
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
