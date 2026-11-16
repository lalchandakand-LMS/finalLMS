<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class CPanelQuotaService
{
    public function getAccountQuota(): array
    {
        $host = trim((string) config('storage-manager.cpanel.host'));
        $username = trim((string) config('storage-manager.cpanel.username'));
        $token = trim((string) config('storage-manager.cpanel.api_token'));

        if ($host === '' || $username === '' || $token === '') {
            return ['available' => false, 'reason' => 'cPanel API credentials are not configured.', 'source' => 'cPanel UAPI'];
        }

        if (str_contains($host, '://') || str_contains($host, '/') || str_contains($host, ':')
            || !preg_match('/^(?=.{1,253}$)[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?$/', $host)) {
            return ['available' => false, 'reason' => 'The configured cPanel API hostname is invalid.', 'source' => 'cPanel UAPI'];
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->withHeaders(['Authorization' => 'cpanel '.$username.':'.$token])
                ->get('https://'.strtolower($host).':2083/execute/Quota/get_quota_info');

            if (!$response->successful()) {
                return ['available' => false, 'reason' => 'The cPanel quota API returned HTTP '.$response->status().'.', 'source' => 'cPanel UAPI'];
            }

            $payload = $response->json();
            $result = $payload['result'] ?? $payload['cpanelresult']['result'] ?? null;
            if (!is_array($result) || (int) ($result['status'] ?? 0) !== 1) {
                return ['available' => false, 'reason' => 'The cPanel quota API did not return a successful result.', 'source' => 'cPanel UAPI'];
            }

            $data = $result['data'] ?? [];
            if (is_array($data) && array_is_list($data)) $data = $data[0] ?? [];
            if (!is_array($data) || !is_numeric($data['megabytes_used'] ?? null)) {
                return ['available' => false, 'reason' => 'The cPanel quota response did not contain a valid usage value.', 'source' => 'cPanel UAPI'];
            }

            $usedMb = (float) $data['megabytes_used'];
            $rawLimit = $data['megabyte_limit'] ?? null;
            $unlimited = !is_numeric($rawLimit) || (float) $rawLimit <= 0;
            $limitMb = $unlimited ? null : (float) $rawLimit;
            $remainMb = $unlimited ? null : (is_numeric($data['megabytes_remain'] ?? null)
                ? max(0, (float) $data['megabytes_remain'])
                : max(0, $limitMb - $usedMb));

            return [
                'available' => true,
                'used_bytes' => (int) round($usedMb * 1048576),
                'limit_bytes' => $limitMb === null ? null : (int) round($limitMb * 1048576),
                'free_bytes' => $remainMb === null ? null : (int) round($remainMb * 1048576),
                'usage_percent' => $limitMb && $limitMb > 0 ? min(100, round(($usedMb / $limitMb) * 100, 1)) : null,
                'unlimited' => $unlimited,
                'source' => 'cPanel UAPI',
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $error) {
            report($error);
            return [
                'available' => false,
                'reason' => 'The cPanel quota API is temporarily unavailable. Check the host, API token and cPanel permissions.',
                'source' => 'cPanel UAPI',
            ];
        }
    }
}
