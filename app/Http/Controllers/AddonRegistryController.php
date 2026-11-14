<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\GoogleDriveConnection;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
                __('This add-on cannot be enabled until its configuration and compatibility checks pass.'));
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

        return redirect()->route('admin.addons.index')->with('success', __('Add-on registry updated. Provider activation still depends on its own server configuration and connection checks.'));
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
        $s3Disk = (array) config('filesystems.disks.s3', []);
        $s3Endpoint = $s3Disk['endpoint'] ?? null;
        $s3Credentials = filled($s3Disk['key'] ?? null)
            && filled($s3Disk['secret'] ?? null)
            && filled($s3Disk['region'] ?? null)
            && filled($s3Disk['bucket'] ?? null);
        $s3EndpointSecure = blank($s3Endpoint) || parse_url((string) $s3Endpoint, PHP_URL_SCHEME) === 'https';
        $s3Ready = config('filesystems.default') === 's3' && $s3Credentials && $s3EndpointSecure;


        $definitions = [
            'bkash' => [
                'name' => __('bKash Tokenized Checkout'),
                'provider' => 'bKash',
                'purpose' => __('Server-verified BDT course and eBook payments'),
                'scopes' => ['payments.create', 'payments.verify', 'payments.callback'],
                'config_ready' => (bool) config('services.bkash.enabled') && $bkashKeysPresent && $bkashHttps,
                'health' => (bool) config('services.bkash.enabled') && $bkashKeysPresent && $bkashHttps ? __('Configuration ready; live merchant verification still required') : __('Missing or disabled server configuration'),
                'blocked' => false,
                'requirements' => __('HTTPS base URL, merchant username/password, app key/secret and BKASH_ENABLED=true'),
            ],
            'google_drive' => [
                'name' => __('Google Drive Storage'),
                'provider' => 'Google',
                'purpose' => __('OAuth-connected backup and storage quota integration'),
                'scopes' => ['storage.quota.read', 'storage.backup.write'],
                'config_ready' => $driveCredentials,
                'health' => ! $driveCredentials ? __('OAuth credentials missing') : ($driveConnected ? __('OAuth account connected') : __('OAuth configured; account connection required')),
                'blocked' => false,
                'requirements' => __('Google OAuth client ID, client secret and redirect URI; connect an account in Storage Manager'),
            ],
            's3_compatible_storage' => [
                'name' => __('S3-compatible Object Storage'),
                'provider' => 'AWS S3 / S3-compatible',
                'purpose' => __('Private object storage for uploads and backups'),
                'scopes' => ['storage.objects.read', 'storage.objects.write', 'storage.bucket.inspect'],
                'config_ready' => $s3Ready,
                'health' => ! $s3Credentials
                    ? __('Missing S3 access key, secret, region or bucket')
                    : (! $s3EndpointSecure
                        ? __('S3-compatible endpoint must use HTTPS')
                        : (config('filesystems.default') === 's3'
                            ? __('S3 configuration ready; live bucket verification still required')
                            : __('S3 credentials present; set FILESYSTEM_DISK=s3 to activate'))),
                'blocked' => false,
                'requirements' => __('FILESYSTEM_DISK=s3, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION and AWS_BUCKET; optional HTTPS AWS_ENDPOINT'),
            ],
            'lms_updates' => [
                'name' => __('Signed LMS Update Center'),
                'provider' => 'LMS release service',
                'purpose' => __('Verify signed release manifests and stage update packages'),
                'scopes' => ['updates.manifest.read', 'updates.package.verify', 'updates.backup.prepare'],
                'config_ready' => (bool) config('services.lms_updates.enabled')
                    && filter_var(config('services.lms_updates.manifest_url'), FILTER_VALIDATE_URL)
                    && parse_url((string) config('services.lms_updates.manifest_url'), PHP_URL_SCHEME) === 'https'
                    && is_file($updatePublicKey),
                'health' => (bool) config('services.lms_updates.enabled')
                    && filter_var(config('services.lms_updates.manifest_url'), FILTER_VALIDATE_URL)
                    && parse_url((string) config('services.lms_updates.manifest_url'), PHP_URL_SCHEME) === 'https'
                    && is_file($updatePublicKey) ? __('Signed-update configuration ready') : __('Disabled or missing HTTPS manifest/public key'),
                'blocked' => false,
                'requirements' => __('LMS_UPDATES_ENABLED=true, HTTPS manifest URL and provisioned release public key'),
            ],
            'nagad_api' => [
                'name' => __('Nagad Direct API'),
                'provider' => 'Nagad',
                'purpose' => __('Future server-verified payment adapter'),
                'scopes' => ['payments.create', 'payments.verify', 'payments.callback'],
                'config_ready' => false,
                'health' => __('Blocked pending official merchant API guide and credentials'),
                'blocked' => true,
                'requirements' => __('Official merchant integration guide, sandbox credentials, signing/encryption details and callback specification'),
            ],
        ];

        foreach ($definitions as $key => &$addon) {
            $addon['compatible'] = $compatible;
            $addon['enabled'] = (bool) ($saved[$key] ?? false);
            $addon['effective'] = $addon['enabled'] && $addon['config_ready'] && $compatible && ! $addon['blocked'];
            $addon['status'] = $addon['blocked'] ? __('Blocked') : (! $compatible ? __('Incompatible') : ($addon['config_ready'] ? __('Configured') : __('Needs configuration')));
        }
        unset($addon);

        return $definitions;
    }
}
