<?php

namespace App\Services;

use App\Models\GoogleDriveConnection;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleDriveQuotaService
{
    public function getAccountQuota(): array
    {
        $connection = GoogleDriveConnection::query()->active()->latest('updated_at')->first();

        if (!$connection) {
            return ['connected' => false, 'available' => false, 'reason' => 'No Google account is connected.'];
        }

        $token = $this->freshAccessToken($connection);
        if (!$token) {
            return ['connected' => true, 'available' => false, 'email' => $connection->google_email, 'reason' => 'The Google Drive authorization needs to be reconnected.'];
        }

        try {
            $response = $this->quotaRequest($token);
            if ($response->status() === 401) {
                $token = $this->refreshAccessToken($connection);
                if ($token) $response = $this->quotaRequest($token);
            }

            if (!$response->successful()) {
                return ['connected' => true, 'available' => false, 'email' => $connection->google_email, 'reason' => 'Google Drive did not return storage quota information.'];
            }

            $quota = $response->json('storageQuota', []);
            $used = is_numeric($quota['usage'] ?? null) ? (int) $quota['usage'] : null;
            if ($used === null) {
                return ['connected' => true, 'available' => false, 'email' => $connection->google_email, 'reason' => 'Google Drive returned an invalid storage usage value.'];
            }

            $limit = is_numeric($quota['limit'] ?? null) ? (int) $quota['limit'] : null;
            $connection->forceFill(['last_checked_at' => now()])->save();

            return [
                'connected' => true,
                'available' => true,
                'email' => $connection->google_email,
                'used_bytes' => $used,
                'limit_bytes' => $limit,
                'free_bytes' => $limit === null ? null : max(0, $limit - $used),
                'usage_percent' => $limit && $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : null,
                'unlimited' => $limit === null || $limit <= 0,
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $error) {
            report($error);
            return ['connected' => true, 'available' => false, 'email' => $connection->google_email, 'reason' => 'Google Drive storage information is temporarily unavailable.'];
        }
    }

    private function quotaRequest(string $accessToken)
    {
        return Http::acceptJson()->withToken($accessToken)->timeout(10)->get(
            'https://www.googleapis.com/drive/v3/about',
            ['fields' => 'user(emailAddress),storageQuota(limit,usage,usageInDrive,usageInDriveTrash)']
        );
    }

    private function freshAccessToken(GoogleDriveConnection $connection): ?string
    {
        if ($connection->token_expires_at && now()->lt($connection->token_expires_at->subMinute())) {
            $token = $connection->accessTokenValue();
            if ($token) return $token;
        }

        return $this->refreshAccessToken($connection);
    }

    private function refreshAccessToken(GoogleDriveConnection $connection): ?string
    {
        $refreshToken = $connection->refreshTokenValue();
        $clientId = (string) config('services.google_drive.client_id');
        $clientSecret = (string) config('services.google_drive.client_secret');

        if (!$refreshToken || $clientId === '' || $clientSecret === '') return null;

        $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful() || !is_string($response->json('access_token'))) return null;

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);
        $connection->setEncryptedTokens($accessToken, null, $expiresIn);

        return $accessToken;
    }
}
