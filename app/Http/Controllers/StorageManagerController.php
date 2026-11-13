<?php

namespace App\Http\Controllers;

use App\Models\GoogleDriveConnection;
use App\Services\CPanelQuotaService;
use App\Services\GoogleDriveQuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StorageManagerController extends Controller
{
    public function index(CPanelQuotaService $cpanelQuota, GoogleDriveQuotaService $googleDriveQuota): View
    {
        return view('admin.storage.index', [
            'cpanelQuota' => $cpanelQuota->getAccountQuota(),
            'driveQuota' => $googleDriveQuota->getAccountQuota(),
            'googleDriveConfigured' => (bool) config('services.google_drive.client_id') && (bool) config('services.google_drive.client_secret'),
            'configuredLocalQuotaBytes' => (int) config('storage-manager.local_quota_bytes'),
            'warningThresholds' => config('storage-manager.warning_thresholds', [70, 85, 95]),
        ]);
    }

    public function connectGoogleDrive(Request $request): RedirectResponse
    {
        $clientId = (string) config('services.google_drive.client_id');
        $clientSecret = (string) config('services.google_drive.client_secret');
        $redirectUri = (string) config('services.google_drive.redirect_uri');

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            return redirect()->route('admin.storage.index')->with('error', 'Google Drive OAuth credentials are not configured on the server.');
        }

        $state = Str::random(64);
        $request->session()->put('google_drive_oauth_state', $state);
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function googleDriveCallback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_drive_oauth_state');
        $providedState = (string) $request->query('state', '');
        abort_unless(is_string($expectedState) && $expectedState !== '' && hash_equals($expectedState, $providedState), 403);

        if ($request->filled('error')) {
            return redirect()->route('admin.storage.index')->with('error', 'Google authorization was cancelled or denied.');
        }

        $code = $request->query('code');
        if (!is_string($code) || $code === '') {
            return redirect()->route('admin.storage.index')->with('error', 'Google did not return an authorization code.');
        }

        $tokenResponse = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'redirect_uri' => config('services.google_drive.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        $tokenData = $tokenResponse->json();
        if (!$tokenResponse->successful() || !is_string($tokenData['access_token'] ?? null) || !is_numeric($tokenData['expires_in'] ?? null)) {
            return redirect()->route('admin.storage.index')->with('error', 'Google could not complete the Drive authorization.');
        }

        $profileResponse = Http::acceptJson()->withToken($tokenData['access_token'])->timeout(10)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');
        $email = $profileResponse->json('email');

        if (!$profileResponse->successful() || !is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || $profileResponse->json('email_verified') !== true) {
            return redirect()->route('admin.storage.index')->with('error', 'Google did not provide a verified account email.');
        }

        $existing = GoogleDriveConnection::query()->where('google_email', $email)->first();
        $refreshToken = $tokenData['refresh_token'] ?? $existing?->refreshTokenValue();
        if (!is_string($refreshToken) || $refreshToken === '') {
            return redirect()->route('admin.storage.index')->with('error', 'Google did not issue a refresh token. Reconnect and approve offline access.');
        }

        DB::transaction(function () use ($email, $tokenData, $refreshToken): void {
            GoogleDriveConnection::query()->active()->update(['is_active' => false]);
            $connection = GoogleDriveConnection::query()->firstOrNew(['google_email' => $email]);
            $connection->forceFill(['is_active' => true])->save();
            $connection->setEncryptedTokens(
                $tokenData['access_token'],
                $refreshToken,
                (int) $tokenData['expires_in']
            );
        });

        return redirect()->route('admin.storage.index')->with('success', 'Google account connected. Shared Drive/Gmail storage quota is now available.');
    }

    public function disconnectGoogleDrive(): RedirectResponse
    {
        $connection = GoogleDriveConnection::query()->active()->latest('updated_at')->first();
        if (!$connection) {
            return redirect()->route('admin.storage.index')->with('status', 'No Google account is currently connected.');
        }

        $refreshToken = $connection->refreshTokenValue();
        if ($refreshToken) {
            try {
                Http::asForm()->timeout(5)->post('https://oauth2.googleapis.com/revoke', ['token' => $refreshToken]);
            } catch (\Throwable $error) {
                report($error);
            }
        }

        $connection->forceFill([
            'is_active' => false,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ])->save();

        return redirect()->route('admin.storage.index')->with('status', 'Google account disconnected. Files already stored in Drive were not deleted.');
    }
}
