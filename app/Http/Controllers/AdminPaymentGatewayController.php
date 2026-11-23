<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\Payments\MfsMerchantConfigurationService;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;

class AdminPaymentGatewayController extends Controller
{
    public function index(PaymentGatewayManager $gateways, MfsMerchantConfigurationService $mfs): View
    {
        $configurations = [];
        foreach ($mfs->providers() as $key => $label) {
            $configuration = $mfs->configuration($key);
            $configurations[$key] = [
                'label' => $label,
                'configured' => $configuration !== null,
                'updated_at' => $configuration?->updated_at,
            ];
        }
        return view('admin.payment-gateways.index', [
            'gateways' => $gateways->catalog(),
            'mfsConfigurations' => $configurations,
        ]);
    }

    public function update(Request $request, string $key, PaymentGatewayManager $gateways): RedirectResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $catalog = $gateways->catalog();
        abort_unless(isset($catalog[$key]), 404);
        $enabled = (bool) $validated['enabled'];
        if ($enabled) {
            abort_unless($catalog[$key]['configured'], 422,
                __('This payment gateway cannot be enabled until its server configuration is complete.'));
        }
        $legacyDefault = ['bkash' => (bool) (Setting::paymentMethods()['bkash']['enabled'] ?? false) || (bool) config('services.bkash.enabled')];
        $saved = json_decode((string) Setting::value('payment_gateway_registry', json_encode($legacyDefault)), true);
        $saved = is_array($saved) ? $saved : [];
        $saved[$key] = $enabled;
        Setting::setValue('payment_gateway_registry', json_encode($saved, JSON_THROW_ON_ERROR));
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => 'admin.payment_gateways.updated',
            'description' => ($enabled ? 'Enabled' : 'Disabled').' payment gateway: '.$key,
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
        return redirect()->route('admin.payment-gateways.index')
            ->with('success', __('Payment gateway registry updated. Live payments still require provider sandbox acceptance and merchant approval.'));
    }

    public function updateMfs(Request $request, string $provider, MfsMerchantConfigurationService $mfs): RedirectResponse
    {
        abort_unless(array_key_exists($provider, $mfs->providers()), 404);
        $validated = $request->validate([
            'mode' => ['required', 'in:sandbox'],
            'credentials_json' => ['required', 'string', 'max:12000'],
        ]);
        try {
            $credentials = json_decode($validated['credentials_json'], true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return back()->withErrors(['credentials_json' => __('Enter valid JSON credentials.')]);
        }
        if (! is_array($credentials) || array_is_list($credentials) || $credentials === []) {
            return back()->withErrors(['credentials_json' => __('Credentials must be a non-empty JSON object.')]);
        }
        foreach ($credentials as $key => $value) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $key) !== 1
                || ! is_string($value) || $value === '' || mb_strlen($value) > 4096) {
                return back()->withErrors(['credentials_json' => __('Each credential must have a valid key and a non-empty string value (maximum 4096 characters).')]);
            }
        }
        $mfs->save($provider, $credentials, (int) $request->user()->id);
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => 'admin.mfs_merchant_configuration.updated',
            'description' => 'Updated '.$provider.' merchant configuration (sandbox). Credential values were not logged.',
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
        return redirect()->route('admin.payment-gateways.index')
            ->with('success', __('Merchant configuration encrypted and saved. Live mode is not available in this foundation release.'));
    }
}
