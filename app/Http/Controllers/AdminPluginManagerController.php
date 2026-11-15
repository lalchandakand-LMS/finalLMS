<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PluginInstallation;
use App\Models\PluginSetting;
use App\Models\PluginVersionRecord;
use App\Services\Plugins\PluginUploadInstaller;
use App\Services\Plugins\PluginUninstaller;
use App\Services\Plugins\PluginDeactivator;
use App\Services\Plugins\PluginSettingsManager;
use App\Services\Plugins\PluginHealthChecker;
use App\Services\Plugins\PluginBackupRestorer;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class AdminPluginManagerController extends Controller
{
    public function index(ModuleRegistry $modules): View
    {
        return view('admin.plugins.index', [
            'plugins' => PluginInstallation::query()->orderBy('plugin_key')->get(),
            'firstPartyModules' => $modules->catalog(),
            'settingsByPlugin' => PluginSetting::query()->get(['plugin_key', 'setting_key', 'updated_at'])->groupBy('plugin_key'),
            'versionHistoryByPlugin' => PluginVersionRecord::query()->orderByDesc('installed_at')->get()->groupBy('plugin_key'),
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

            return back()->withInput($request->except('package'))->with(
                'error',
                __('Plugin installation failed. The package was not activated; check the manifest, signature, trusted key and server logs.')
            );
        }

        try {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'admin.plugins.installed',
                'description' => 'Installed signed plugin '.$plugin->plugin_key.' version '.$plugin->version,
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->route('admin.plugins.index')->with(
            'success',
            __('Plugin package verified and installed. Activation remains disabled until the isolated plugin runtime is complete.')
        );
    }
    public function healthCheck(Request $request, PluginInstallation $plugin, PluginHealthChecker $checker): RedirectResponse
    {
        $checked = $checker->check($plugin);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => 'admin.plugins.health_checked',
            'description' => 'Plugin health check '.$checked->health_status.' for '.$checked->plugin_key.'.',
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        if ($checked->health_status !== 'healthy') {
            return back()->with('error', __('Plugin health check failed. The package must remain inactive pending review.'));
        }

        return back()->with('success', __('Plugin package integrity and signing trust verified.'));
    }

    public function updateSettings(Request $request, PluginInstallation $plugin, PluginSettingsManager $settings): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'setting_key' => ['required', 'string', 'max:120', 'regex:/^[a-z][a-z0-9_.-]{0,119}$/'],
            'value' => ['required', 'string', 'min:1', 'max:4096'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except('value'));
        }

        $validated = $validator->validated();

        try {
            $settings->set($plugin, $validated['setting_key'], $validated['value']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception instanceof InvalidArgumentException || $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : __('Unable to save plugin setting; check server logs.'));
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => 'admin.plugins.setting_updated',
            'description' => 'Updated encrypted setting '.$validated['setting_key'].' for plugin '.$plugin->plugin_key.'.',
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return redirect()->route('admin.plugins.index')->with('success', __('Plugin setting saved securely. The value will not be displayed again.'));
    }

    public function deactivate(Request $request, PluginInstallation $plugin, PluginDeactivator $deactivator): RedirectResponse
    {
        try {
            $updated = $deactivator->deactivate($plugin);
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'admin.plugins.deactivated',
                'description' => 'Deactivated plugin '.$updated->plugin_key.' version '.$updated->version.'.',
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            try {
                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'event' => 'admin.plugins.deactivate_failed',
                    'description' => 'Plugin deactivation was rejected for '.$plugin->plugin_key.'.',
                    'method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]);
            } catch (Throwable $loggingException) {
                report($loggingException);
            }

            return back()->with('error', $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : __('Plugin deactivation failed; check server logs.'));
        }

        return redirect()->route('admin.plugins.index')->with('success', __('Plugin deactivated. Its files and data were preserved.'));
    }

    public function restore(Request $request, PluginInstallation $plugin, PluginBackupRestorer $restorer): RedirectResponse
    {
        try {
            $restored = $restorer->restore($plugin);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'admin.plugins.restored',
                'description' => 'Restored plugin '.$restored->plugin_key.' version '.$restored->version.' from its verified private backup.',
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            try {
                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'event' => 'admin.plugins.restore_failed',
                    'description' => 'Plugin backup restore was rejected for '.$plugin->plugin_key.'.',
                    'method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]);
            } catch (Throwable $loggingException) {
                report($loggingException);
            }

            return back()->with('error', $exception instanceof InvalidArgumentException || $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : __('Plugin restore failed. The backup was preserved where possible; check server logs.'));
        }

        return redirect()->route('admin.plugins.index')->with('success', __('Plugin backup verified and restored. The plugin remains inactive.'));
    }

    public function uninstall(Request $request, PluginInstallation $plugin, PluginUninstaller $uninstaller): RedirectResponse
    {
        $request->validate(['confirm_uninstall' => ['required', 'accepted']]);

        try {
            $uninstalled = $uninstaller->uninstall($plugin);
        } catch (Throwable $exception) {
            report($exception);
            try {
                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'event' => 'admin.plugins.uninstall_failed',
                    'description' => 'Plugin uninstall was rejected for '.$plugin->plugin_key.'.',
                    'method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]);
            } catch (Throwable $loggingException) {
                report($loggingException);
            }

            return back()->with('error', $exception instanceof InvalidArgumentException || $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : __('Plugin uninstall failed. The package backup was preserved where possible; check server logs.'));
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => 'admin.plugins.uninstalled',
            'description' => 'Moved plugin '.$uninstalled->plugin_key.' version '.$uninstalled->version.' to private backup.',
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return redirect()->route('admin.plugins.index')->with('success', __('Plugin uninstalled. Its files were retained in a private backup; plugin data was not deleted.'));
    }

    private function recordFailure(Request $request): void
    {
        try {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'admin.plugins.install_failed',
                'description' => 'A plugin package installation attempt was rejected or failed validation.',
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
