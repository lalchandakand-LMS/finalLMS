<?php

namespace App\Http\Controllers;

use App\Services\Integrations\ApiConnectorManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class ApiConnectorAdminController extends Controller
{
    public function index(ApiConnectorManager $manager): View
    {
        return view('admin.integrations.index', ['connectors' => $manager->catalog()]);
    }

    public function connect(string $provider, Request $request, ApiConnectorManager $manager): RedirectResponse
    {
        $driver = $manager->driver($provider);
        $rules = [];
        foreach ($driver->credentialSchema() as $key => $field) {
            $rules[$key] = [$field['required'] ? 'required' : 'nullable', 'string', 'max:2000'];
        }
        $credentials = $request->validate($rules);
        try {
            $manager->connect($provider, $credentials);
            return back()->with('success', __('Credentials verified. Complete webhook verification before the connector is marked connected.'));
        } catch (Throwable $exception) {
            return back()->withErrors(['provider' => __('The provider could not verify these credentials. Check the account, permissions and token.')]);
        }
    }

    public function pause(string $provider, ApiConnectorManager $manager): RedirectResponse
    {
        $manager->pause($provider);
        return back()->with('success', __('Connector paused.'));
    }

    public function disconnect(string $provider, ApiConnectorManager $manager): RedirectResponse
    {
        $manager->disconnect($provider);
        return back()->with('success', __('Connector disconnected. Stored credentials were revoked from this LMS connection.'));
    }
}
