<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPaymentGatewayController extends Controller
{
    public function index(PaymentGatewayManager $gateways): View
    {
        return view('admin.payment-gateways.index', [
            'gateways' => $gateways->catalog(),
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

        $saved = json_decode((string) Setting::value('payment_gateway_registry', Setting::value('addon_registry', '{}')), true);
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
}
