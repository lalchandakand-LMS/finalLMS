<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use SodiumException;

final class PluginSignatureVerifier
{
    public function __construct(private readonly PluginManifestValidator $manifestValidator)
    {
    }

    public function verifyAndRecord(PluginInstallation $plugin, array $manifest, string $stagedArchivePath, string $signatureBase64, string $keyId, string $finalRelativePath): PluginInstallation
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('The PHP sodium extension is required for plugin signature verification.');
        }

        $errors = $this->manifestValidator->errors($manifest);
        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid plugin manifest: '.implode(' ', $errors));
        }

        if (! hash_equals((string) $plugin->plugin_key, (string) $manifest['key'])
            || (string) $plugin->version !== (string) $manifest['version']
            || (string) $plugin->name !== (string) $manifest['name']
            || (string) $plugin->type !== (string) $manifest['type']) {
            throw new InvalidArgumentException('The signed manifest does not match the registered plugin identity.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}$/D', $keyId) !== 1) {
            throw new InvalidArgumentException('Invalid trusted release key ID.');
        }

        $keys = config('plugins.trusted_public_keys', []);
        $encodedPublicKey = is_array($keys) ? ($keys[$keyId] ?? null) : null;
        if (! is_string($encodedPublicKey) || $encodedPublicKey === '') {
            throw new RuntimeException('The plugin signing key is not trusted.');
        }

        $publicKey = base64_decode($encodedPublicKey, true);
        if (! is_string($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('The configured trusted public key is invalid.');
        }

        $signature = base64_decode($signatureBase64, true);
        if (! is_string($signature) || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new InvalidArgumentException('The detached plugin signature is malformed.');
        }

        $archive = $this->resolveStagedArchive($stagedArchivePath);
        $size = filesize($archive);
        $maxBytes = max(1, (int) config('plugins.max_package_bytes', 52428800));
        if ($size === false || $size < 1 || $size > $maxBytes) {
            throw new RuntimeException('The staged plugin package is empty or exceeds the configured size limit.');
        }

        $checksum = hash_file('sha256', $archive);
        if (! is_string($checksum)) {
            throw new RuntimeException('Unable to calculate the staged plugin package checksum.');
        }

        $this->assertFinalPackagePath($finalRelativePath);
        [$vendor, $slug] = explode('.', $manifest['key'], 2);
        $expectedPath = 'plugins/'.$vendor.'/'.str_replace('.', '-', $slug).'/'.$manifest['version'];
        if (! hash_equals($expectedPath, $finalRelativePath)) {
            throw new InvalidArgumentException('Plugin destination path does not match its signed identity.');
        }
        $payload = self::payloadForSignature($manifest, $checksum);

        try {
            $valid = sodium_crypto_sign_verify_detached($signature, $payload, $publicKey);
        } catch (SodiumException $exception) {
            throw new RuntimeException('Plugin signature verification failed.', previous: $exception);
        }

        if (! $valid) {
            throw new RuntimeException('The plugin package signature is invalid.');
        }

        return DB::transaction(function () use ($plugin, $manifest, $checksum, $keyId, $finalRelativePath): PluginInstallation {
            $locked = PluginInstallation::query()->lockForUpdate()->findOrFail($plugin->getKey());

            if (! in_array($locked->status, ['validated', 'staged'], true)) {
                throw new RuntimeException('Only a validated or staged plugin can receive package verification metadata.');
            }

            if (! hash_equals((string) $locked->plugin_key, (string) $manifest['key'])
                || (string) $locked->version !== (string) $manifest['version']) {
                throw new RuntimeException('The plugin registry identity changed during signature verification.');
            }

            $locked->forceFill([
                'manifest' => $manifest,
                'package_sha256' => $checksum,
                'signature_key_id' => $keyId,
                'signature_verified_at' => now(),
                'package_path' => $finalRelativePath,
            ])->save();

            return $locked->refresh();
        });
    }

    public static function payloadForSignature(array $manifest, string $sha256): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new InvalidArgumentException('A lowercase SHA-256 checksum is required.');
        }

        $payload = [
            'manifest' => self::sortObjectKeys($manifest),
            'package_sha256' => $sha256,
            'schema' => 'aloms-plugin-release-v1',
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function sortObjectKeys(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(static fn ($item) => is_array($item) ? self::sortObjectKeys($item) : $item, $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortObjectKeys($item);
            }
        }

        return $value;
    }

    private function resolveStagedArchive(string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || is_link($path) || ! is_file($path)) {
            throw new InvalidArgumentException('The staged plugin archive is not a regular file.');
        }

        $pluginsDirectory = base_path('plugins');
        $stagingDirectory = (string) config('plugins.staging_directory');
        if (is_link($pluginsDirectory) || is_link($stagingDirectory)) {
            throw new InvalidArgumentException('The private plugins and staging directories must not be symbolic links.');
        }

        $pluginsRoot = realpath($pluginsDirectory);
        $root = realpath($stagingDirectory);
        $real = realpath($path);
        if ($pluginsRoot === false || $root === false || $real === false
            || ! str_starts_with($root, rtrim($pluginsRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The private plugin staging directory or archive does not exist.');
        }

        $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($real, $prefix) || ! is_readable($real)) {
            throw new InvalidArgumentException('Plugin archives must be verified from the private plugins/.staging directory.');
        }

        return $real;
    }

    private function assertFinalPackagePath(string $path): void
    {
        if (strlen($path) > 500 || str_contains($path, '\\') || str_contains($path, ':')
            || str_contains($path, "\0") || str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Invalid private plugin destination path.');
        }

        $segments = explode('/', $path);
        if (count($segments) !== 4 || $segments[0] !== 'plugins') {
            throw new InvalidArgumentException('Plugin destination must be plugins/<vendor>/<plugin>/<version>.');
        }

        foreach (array_slice($segments, 1) as $segment) {
            if ($segment === '.' || $segment === '..'
                || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,119}$/D', $segment) !== 1) {
                throw new InvalidArgumentException('Plugin destination contains an unsafe path segment.');
            }
        }
    }
}
