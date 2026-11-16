<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AdminModuleRegistryController extends Controller
{
    public function index(ModuleRegistry $registry): View
    {
        return view('admin.modules.index', ['modules' => $registry->catalog()]);
    }

    public function update(Request $request, string $key, ModuleRegistry $registry): RedirectResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = (bool) $validated['enabled'];

        try {
            $registry->setEnabled($key, $enabled);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => 'admin.modules.updated',
            'description' => ($enabled ? 'Enabled' : 'Disabled').' LMS module: '.$key,
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return redirect()->route('admin.modules.index')->with('success', __('Module registry updated. Existing routes are being migrated in stages.'));
    }
}
