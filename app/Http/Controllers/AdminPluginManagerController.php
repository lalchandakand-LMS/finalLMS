<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PluginInstallation;
use App\Services\Plugins\PluginUploadInstaller;
use App\Services\Plugins\PluginUninstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
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
