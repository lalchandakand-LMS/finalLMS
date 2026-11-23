<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ApiConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class AdminApiConnectionController extends Controller
{
    private const PROVIDERS = [
        'whatsapp_cloud' => 'WhatsApp Business Platform',
        'facebook_messenger' => 'Facebook Messenger',
        'instagram_messaging' => 'Instagram Messaging',
        'telegram_bot' => 'Telegram Bot',
        'email_smtp' => 'Email / SMTP',
        'sms_gateway' => 'SMS Gateway',
        'meta_marketing' => 'Meta Marketing API',
    ];

    public function index(): View
    {
        return view('admin.integrations.index', [
            'connections' => ApiConnection::query()->latest()->paginate(20),
            'providers' => self::PROVIDERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider_key' => ['required', 'string', 'in:'.implode(',', array_keys(self::PROVIDERS))],
            'display_name' => ['required', 'string', 'max:120'],
            'external_account_id' => ['nullable', 'string', 'max:190'],
            'credentials' => ['required', 'array', 'min:1'],
            'credentials.*' => ['nullable', 'string', 'max:10000'],
            'scope_input' => ['nullable', 'string', 'max:4000'],
        ]);

        $credentials = array_filter($data['credentials'], static fn ($value): bool => is_string($value) && trim($value) !== '');
        abort_if($credentials === [], 422, 'At least one credential value is required.');

        $connection = ApiConnection::query()->create([
            'provider_key' => $data['provider_key'],
            'display_name' => $data['display_name'],
            'external_account_id' => $data['external_account_id'] ?? null,
            'credentials_encrypted' => Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR)),
            'granted_scopes' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['scope_input'] ?? ''))))),
            'status' => 'pending_verification',
            'webhook_verified' => false,
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'event' => 'api_connection.created',
            'description' => 'Created API connection '.$connection->provider_key.' ('.$connection->status.').',
            'method' => $request->method(),
            'route' => optional($request->route())->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return back()->with('success', 'Credentials saved securely. Verify provider permissions and webhook before enabling this connection.');
    }

    public function pause(ApiConnection $connection, Request $request): RedirectResponse
    {
        $connection->update(['status' => 'paused']);
        ActivityLog::query()->create(['user_id' => $request->user()->id, 'event' => 'api_connection.paused', 'description' => 'Paused API connection '.$connection->provider_key.'.', 'method' => $request->method(), 'route' => optional($request->route())->getName(), 'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000)]);

        return back()->with('success', 'Connection paused. Existing credentials and audit history were preserved.');
    }

    public function disconnect(ApiConnection $connection, Request $request): RedirectResponse
    {
        $connection->update([
            'status' => 'disconnected',
            'credentials_encrypted' => null,
            'webhook_verified' => false,
            'capabilities' => null,
        ]);
        ActivityLog::query()->create(['user_id' => $request->user()->id, 'event' => 'api_connection.disconnected', 'description' => 'Disconnected API connection '.$connection->provider_key.'.', 'method' => $request->method(), 'route' => optional($request->route())->getName(), 'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000)]);

        return back()->with('success', 'Connection credentials removed. Provider-side token revocation may still be required.');
    }
}