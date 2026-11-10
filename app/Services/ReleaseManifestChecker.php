<?php

namespace App\Services;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ReleaseManifestChecker
{
    private const PRODUCT = 'aloms-english-center-lms';

    public function check(): array
    {
        $settings = config('services.lms_updates');
        $currentVersion = (string) ($settings['current_version'] ?? '0.0.0');

        if (! ($settings['enabled'] ?? false)) {
            throw new RuntimeException('Release checks are disabled until a trusted release key and manifest URL are configured.');
        }

        $manifestUrl = (string) ($settings['manifest_url'] ?? '');
        $keyPath = (string) ($settings['public_key_path'] ?? '');
        if ($keyPath !== '' && ! str_starts_with($keyPath, DIRECTORY_SEPARATOR)) {
            $keyPath = base_path($keyPath);
        }

        if (! $this->isTrustedManifestUrl($manifestUrl)) {
            throw new RuntimeException('The configured release manifest URL is not an approved HTTPS GitHub release URL.');
        }

        if ($keyPath === '' || ! is_file($keyPath) || is_link($keyPath) || ! is_readable($keyPath)) {
            throw new RuntimeException('The trusted release public key is missing or unreadable.');
        }

        $realKeyPath = realpath($keyPath);
        $publicRoot = realpath(public_path());
        if ($realKeyPath === false || ($publicRoot !== false && str_starts_with($realKeyPath, $publicRoot.DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('The release public key must be stored outside the public web root.');
        }

        $manifestBytes = $this->fetchTrustedAsset($manifestUrl, 262144);

        $signatureUrl = preg_replace('/release-manifest\\.json$/', 'release-manifest.sig', $manifestUrl);
        if (! is_string($signatureUrl) || $signatureUrl === $manifestUrl) {
            throw new RuntimeException('The release signature URL could not be derived safely.');
        }

        $signature = $this->fetchTrustedAsset($signatureUrl, 8192);

        $publicPem = file_get_contents($realKeyPath);
        if (! is_string($publicPem) || $publicPem === '') {
            throw new RuntimeException('The trusted release public key could not be read.');
        }

        $publicKey = openssl_pkey_get_public($publicPem);
        if ($publicKey === false) {
            throw new RuntimeException('The configured release public key is not valid PEM.');
        }

        $keyDetails = openssl_pkey_get_details($publicKey);
        if (! is_array($keyDetails) || ($keyDetails['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || ($keyDetails['bits'] ?? 0) < 3072) {
            throw new RuntimeException('The trusted release key must be RSA with at least 3072 bits.');
        }

        $derBase64 = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $publicPem);
        $der = is_string($derBase64) ? base64_decode($derBase64, true) : false;
        if (! is_string($der)) {
            throw new RuntimeException('The trusted public key fingerprint could not be calculated.');
        }
        $fingerprint = hash('sha256', $der);

        $signatureStatus = openssl_verify($manifestBytes, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($signatureStatus !== 1) {
            throw new RuntimeException('The release manifest signature is invalid. The package will not be trusted.');
        }

        try {
            $manifest = json_decode($manifestBytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('The signed release manifest is not valid JSON.');
        }

        if (! is_array($manifest) || array_is_list($manifest)) {
            throw new RuntimeException('The signed release manifest must be a JSON object.');
        }

        $this->validateManifest($manifest, $fingerprint, $settings);

        if (! preg_match('/^\d+\.\d+\.\d+$/', $currentVersion)) {
            throw new RuntimeException('The installed LMS version is not a valid semantic version.');
        }

        $versionComparison = version_compare($manifest['version'], $currentVersion);
        if ($versionComparison < 0) {
            throw new RuntimeException('The configured release channel points to an older version. Downgrades are blocked.');
        }
        $available = $versionComparison > 0;
        $details = [
            'version' => $manifest['version'],
            'release_channel' => $manifest['release_channel'],
            'git_commit' => $manifest['git_commit'],
            'published_at' => $manifest['published_at'],
            'minimum_php' => $manifest['minimum_php'],
            'laravel_major' => $manifest['laravel_major'],
            'required_extensions' => $manifest['required_extensions'],
            'package_sha256' => $manifest['package_sha256'],
            'migration_set_sha256' => $manifest['migration_set_sha256'],
            'artifact_url' => $manifest['artifact_url'],
            'signing_public_key_sha256' => $manifest['signing_public_key_sha256'],
        ];

        return [
            'status' => $available ? 'available' : 'up_to_date',
            'available' => $available,
            'current_version' => $currentVersion,
            'latest_version' => $manifest['version'],
            'manifest_sha256' => hash('sha256', $manifestBytes),
            'git_commit' => $manifest['git_commit'],
            'message' => $available ? 'A newer signed LMS release is available. Installation is not enabled yet.' : 'The installed LMS version is up to date.',
            'details' => $details,
        ];
    }

    private function isTrustedManifestUrl(string $url): bool
    {
        return (bool) preg_match(
            '#^https://github\.com/lalchandakand-LMS/finalLMS/releases/(?:latest/download|download/v\d+\.\d+\.\d+)/release-manifest\.json$#',
            $url
        );
    }

    private function validateManifest(array $manifest, string $fingerprint, array $settings): void
    {
        $required = [
            'product', 'version', 'release_channel', 'git_commit', 'minimum_php',
            'laravel_major', 'required_extensions', 'package_file', 'package_sha256',
            'migration_set_sha256', 'signing_public_key_sha256', 'published_at', 'artifact_url',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new RuntimeException('The signed release manifest is missing a required field.');
            }
        }

        if (($manifest['product'] ?? null) !== self::PRODUCT
            || ! is_string($manifest['version'])
            || ! preg_match('/^\d+\.\d+\.\d+$/', $manifest['version'])
            || ($manifest['release_channel'] ?? null) !== ($settings['channel'] ?? 'stable')
            || ($manifest['package_file'] ?? null) !== 'AlomsEnglishCenter-cpanel.zip'
            || ! is_string($manifest['git_commit']) || ! preg_match('/^[a-f0-9]{40}$/', $manifest['git_commit'])
            || ! is_string($manifest['package_sha256']) || ! preg_match('/^[a-f0-9]{64}$/', $manifest['package_sha256'])
            || ! is_string($manifest['migration_set_sha256']) || ! preg_match('/^[a-f0-9]{64}$/', $manifest['migration_set_sha256'])
            || ! is_string($manifest['signing_public_key_sha256']) || ! hash_equals($fingerprint, $manifest['signing_public_key_sha256'])
            || ! is_string($manifest['minimum_php']) || ! preg_match('/^[0-9]+\\.[0-9]+\\.[0-9]+$/', $manifest['minimum_php']) || ! is_int($manifest['laravel_major'])
            || ! is_array($manifest['required_extensions']) || ! is_string($manifest['published_at'])) {
            throw new RuntimeException('The signed release manifest contains invalid or incompatible metadata.');
        }

        if (version_compare(PHP_VERSION, $manifest['minimum_php'], '<')
            || $manifest['laravel_major'] !== (int) explode('.', Application::VERSION)[0]) {
            throw new RuntimeException('This release is not compatible with the installed PHP or Laravel version.');
        }

        foreach ($manifest['required_extensions'] as $extension) {
            if (! is_string($extension) || ! preg_match('/^[a-zA-Z0-9_]+$/', $extension) || ! extension_loaded($extension)) {
                throw new RuntimeException('A required PHP extension for this release is missing.');
            }
        }

        $expectedArtifact = 'https://github.com/lalchandakand-LMS/finalLMS/releases/download/v'.$manifest['version'].'/AlomsEnglishCenter-cpanel.zip';
        if (($manifest['artifact_url'] ?? null) !== $expectedArtifact) {
            throw new RuntimeException('The release package URL is not the expected trusted GitHub release asset.');
        }
    }
    private function fetchTrustedAsset(string $url, int $maximumBytes): string
    {
        $allowedHosts = [
            'github.com',
            'objects.githubusercontent.com',
            'release-assets.githubusercontent.com',
        ];

        for ($redirect = 0; $redirect <= 3; $redirect++) {
            $response = Http::connectTimeout(5)->timeout(15)->withoutRedirecting()->get($url);

            if ($response->status() >= 300 && $response->status() < 400) {
                $location = $response->header('Location');
                $parts = is_string($location) ? parse_url($location) : false;
                if (! is_array($parts)
                    || ($parts['scheme'] ?? null) !== 'https'
                    || ! in_array(strtolower((string) ($parts['host'] ?? '')), $allowedHosts, true)
                    || isset($parts['user'])
                    || isset($parts['pass'])
                    || (isset($parts['port']) && $parts['port'] !== 443)) {
                    throw new RuntimeException('The release asset redirected to an untrusted destination.');
                }

                if ($redirect === 3) {
                    throw new RuntimeException('The release asset exceeded the permitted redirect count.');
                }

                $url = $location;
                continue;
            }

            $response->throw();
            $body = $response->body();
            if ($body === '' || strlen($body) > $maximumBytes) {
                throw new RuntimeException('A release metadata asset is empty or exceeds the permitted size.');
            }

            return $body;
        }

        throw new RuntimeException('The release asset could not be retrieved safely.');
    }

}
