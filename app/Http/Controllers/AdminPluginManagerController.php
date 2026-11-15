<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PluginInstallation;
use App\Services\Plugins\PluginIntegrityVerifier;
use App\Services\Plugins\PluginLifecycle;
use App\Services\Plugins\PluginManifestValidator;
use App\Services\Plugins\PluginUploadInstaller;
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
            'plugins' => PluginInstallation::query()->orderBy('plugin_key')->get(),
            'maxPackageBytes' => (int) config('plugins.max_package_bytes', 52428800),
        ]);
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
            return back()->withInput($request->except('package'))->with('error',
                __('Plugin installation failed. The package was not activated; check the manifest, signature, trusted key and server logs.'));
        }

        $this->audit($request, 'admin.plugins.installed', 'Installed signed plugin '.$plugin->plugin_key.' version '.$plugin->version);

        return redirect()->route('admin.plugins.index')->with('success',
            __('Plugin package verified and installed. Review its permissions before activation.'));
    }

    public function activate(
        Request $request,
        string $key,
        PluginManifestValidator $manifestValidator,
        PluginIntegrityVerifier $integrityVerifier,
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

            if (! hash_equals((string) $plugin->plugin_key, (string) $plugin->manifest['key'])
                || (string) $plugin->name !== (string) $plugin->manifest['name']
                || (string) $plugin->version !== (string) $plugin->manifest['version']
                || (string) $plugin->type !== (string) $plugin->manifest['type']) {
                throw new RuntimeException('The plugin manifest identity does not match its installed registry record.');
            }

            $this->assertDependenciesAvailable($plugin);
            $integrityVerifier->verify($plugin);
            $lifecycle->transition($plugin->plugin_key, 'active');
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailure($request, 'admin.plugins.activation_failed');
            return back()->with('error', __('Plugin activation was blocked by a security or dependency check. Review the plugin manifest, dependencies, trusted release key and server logs.'));
        }

        $this->audit($request, 'admin.plugins.activated', 'Activated signed plugin '.$plugin->plugin_key.' version '.$plugin->version);

        return redirect()->route('admin.plugins.index')->with('success',
            __('Plugin activated. Its verified runtime will register on the next request.'));
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

        $this->audit($request, 'admin.plugins.deactivated',
            'Deactivated plugin '.$plugin->plugin_key.'; installed files and data were retained.');

        return redirect()->route('admin.plugins.index')->with('success',
            __('Plugin deactivated. Its installed files and data were retained.'));
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

        $this->audit($request, 'admin.plugins.uninstalled',
            'Uninstalled plugin '.$plugin->plugin_key.'; application data and migration history were retained.');

        return redirect()->route('admin.plugins.index')->with('success',
            __('Plugin uninstalled. Its executable package was removed; plugin data and migration records were retained.'));
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
