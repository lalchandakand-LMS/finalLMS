<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PluginInstallation;
use App\Services\Plugins\PluginActivationService;
use App\Services\Plugins\PluginUploadInstaller;
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
            __('Plugin package verified and installed. Review dependencies and activate it when ready.')
        );
    }

    public function activate(Request $request, string $pluginKey, PluginActivationService $activation): RedirectResponse
    {
        try {
            $plugin = $activation->activate($pluginKey);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordLifecycleEvent($request, 'admin.plugins.activation_failed', 'Activation rejected for '.$pluginKey);

            return back()->with(
                'error',
                $exception instanceof InvalidArgumentException
                    ? $exception->getMessage()
                    : __('Plugin activation preflight failed. Check package integrity, trusted key, dependencies and server logs.')
            );
        }

        $this->recordLifecycleEvent(
            $request,
            'admin.plugins.activated',
            'Activated plugin '.$plugin->plugin_key.' version '.$plugin->version
        );

        return redirect()->route('admin.plugins.index')->with('success', __('Plugin activated. Runtime verification will run on the next request.'));
    }

    public function deactivate(Request $request, string $pluginKey, PluginActivationService $activation): RedirectResponse
    {
        try {
            $plugin = $activation->deactivate($pluginKey);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordLifecycleEvent($request, 'admin.plugins.deactivation_failed', 'Deactivation rejected for '.$pluginKey);

            return back()->with(
                'error',
                $exception instanceof InvalidArgumentException
                    ? $exception->getMessage()
                    : __('Plugin deactivation failed. Check active dependents and server logs.')
            );
        }

        $this->recordLifecycleEvent(
            $request,
            'admin.plugins.deactivated',
            'Deactivated plugin '.$plugin->plugin_key.' version '.$plugin->version
        );

        return redirect()->route('admin.plugins.index')->with('success', __('Plugin deactivated. Its routes are now blocked.'));
    }

    private function recordLifecycleEvent(Request $request, string $event, string $description): void
    {
        try {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => $event,
                'description' => mb_substr($description, 0, 500),
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
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
