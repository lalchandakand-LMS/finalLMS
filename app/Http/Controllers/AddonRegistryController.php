<?php

namespace App\\Http\\Controllers;

use App\\Models\\ActivityLog;
use App\\Models\\GoogleDriveConnection;
use App\\Models\\Setting;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\View\\View;

class AddonRegistryController extends Controller
{
    public function index(): View
    {
        return view('admin.addons.index', ['addons' => $this->addons()]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $addons = $this->addons();
        abort_unless(isset($addons[$key]), 404);

        $enabled = (bool) $validated['enabled'];
        if ($enabled) {
            abort_unless($addons[$key]['config_ready'] && $addons[$key]['compatible'] && ! $addons[$key]['blocked'], 422,
                'This add-on cannot be enabled until its configuration and compatibility checks pass.');
        }

        $saved = json_decode((string) Setting::value('addon_registry', '{}'), true);
        $saved = is_array($saved) ? $saved : [];
        $saved[$key] = $enabled;
        Setting::setValue('addon_registry', json_encode($saved, JSON_THROW_ON_ERROR));

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => 'admin.addons.updated',
            'description' => ($enabled ? 'Enabled' : 'Disabled').' allow-listed add-on registry entry: '.$key,
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return redirect()->route('admin.addons.index')->with('success', 'Add-on registry updated. Provider activation still depends on its own server configuration and connection checks.');
    }

    private function addons(): array
    {
        $saved = json_decode((string) Setting::value('addon_registry', '{}'), true);
        $saved = is_array($saved) ? $saved : [];
        $compatible = PHP_VERSION_ID >= 80300 && (int) explode('.', app()->version())[0] === 12;
        $bkashKeysPresent = collect([
            config('services.bkash.username'),
            config('services.bkash.password'),
            config('services.bkash.app_key'),
            config('services.bkash.app_secret'),
        ])->every(fn ($value) => is_string($value) && trim($value) !== '');
        $bkashHttps = parse_url((string) config('services.bkash.base_url'), PHP_URL_SCHEME) === 'https';
        $driveCredentials = filled(config('services.google_drive.client_id'))
            && filled(config('services.google_drive.client_secret'))
            && filled(config('services.google_drive.redirect_uri'));
        $driveConnected = GoogleDriveConnection::query()->active()->exists();
        $updatePublicKey = (string) config('services.lms_updates.public_key_path');

        $definitions = [
            'bkash' => [
                'name' => 'bKash Tokenized Checkout',
                'provider' => 'bKash',
                'purpose' => 'Server-verified BDT course and eBook payments',
                'scopes' => ['payments.create', 'payments.verify', 'payments.callback'],
                'config_ready' => (bool) config('services.bkash.enabled') && $bkashKeysPresent && $bkashHttps,
                'health' => (bool) config('services.bkash.enabled') && $bkashKeysPresent && $bkashHttps ? 'Configuration ready; live merchant verification still required' : 'Missing or disabled server configuration',
                'blocked' => false,
                'requirements' => 'HTTPS base URL, merchant username/password, app key/secret and BKASH_ENABLED=true',
            ],
            'google_drive' => [
                'name' => 'Google Drive Storage',
                'provider' => 'Google',
                'purpose' => 'OAuth-connected backup and storage quota integration',
                'scopes' => ['storage.quota.read', 'storage.backup.write'],
                'config_ready' => $driveCredentials,
                'health' => ! $driveCredentials ? 'OAuth credentials missing' : ($driveConnected ? 'OAuth account connected' : 'OAuth configured; account connection required'),
                'blocked' => false,
                'requirements' => 'Google OAuth client ID, client secret and redirect URI; connect an account in Storage Manager',
            ],
            'lms_updates' => [
                'name' => 'Signed LMS Update Center',
                'provider' => 'LMS release service',
                'purpose' => 'Verify signed release manifests and stage update packages',
                'scopes' => ['updates.manifest.read', 'updates.package.verify', 'updates.backup.prepare'],
                'config_ready' => (bool) config('services.lms_updates.enabled')
                    && filter_var(config('services.lms_updates.manifest_url'), FILTER_VALIDATE_URL)
                    && parse_url((string) config('services.lms_updates.manifest_url'), PHP_URL_SCHEME) === 'https'
                    && is_file($updatePublicKey),
                'health' => (bool) config('services.lms_updates.enabled')
                    && filter_var(config('services.lms_updates.manifest_url'), FILTER_VALIDATE_URL)
                    && parse_url((string) config('services.lms_updates.manifest_url'), PHP_URL_SCHEME) === 'https'
                    && is_file($updatePublicKey) ? 'Signed-update configuration ready' : 'Disabled or missing HTTPS manifest/public key',
                'blocked' => false,
                'requirements' => 'LMS_UPDATES_ENABLED=true, HTTPS manifest URL and provisioned release public key',
            ],
            'nagad_api' => [
                'name' => 'Nagad Direct API',
                'provider' => 'Nagad',
                'purpose' => 'Future server-verified payment adapter',
                'scopes' => ['payments.create', 'payments.verify', 'payments.callback'],
                'config_ready' => false,
                'health' => 'Blocked pending official merchant API guide and credentials',
                'blocked' => true,
                'requirements' => 'Official merchant integration guide, sandbox credentials, signing/encryption details and callback specification',
            ],
        ];

        foreach ($definitions as $key => &$addon) {
            $addon['compatible'] = $compatible;
            $addon['enabled'] = (bool) ($saved[$key] ?? false);
            $addon['effective'] = $addon['enabled'] && $addon['config_ready'] && $compatible && ! $addon['blocked'];
            $addon['status'] = $addon['blocked'] ? 'Blocked' : (! $compatible ? 'Incompatible' : ($addon['config_ready'] ? 'Configured' : 'Needs configuration'));
        }
        unset($addon);

        return $definitions;
    }
}
