<?php

declare(strict_types=1);

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

$fail = static function (string $message): never {
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
};

if (! extension_loaded('sodium') || ! class_exists(ZipArchive::class)) {
    $fail('The sodium and zip PHP extensions are required.');
}

$pluginRoot = realpath($argv[1] ?? '');
$outputDirectory = $argv[2] ?? '';
$keyId = trim((string) getenv('PLUGIN_RELEASE_KEY_ID'));
$encodedSecret = (string) getenv('PLUGIN_RELEASE_PRIVATE_KEY');

if ($pluginRoot === false || ! is_dir($pluginRoot) || $outputDirectory === '') {
    $fail('Usage: php tools/sign-plugin-release.php <plugin-directory> <private-output-directory>');
}
if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}$/D', $keyId) !== 1) {
    $fail('Set PLUGIN_RELEASE_KEY_ID to the provisioned immutable release key ID.');
}
$secretKey = base64_decode($encodedSecret, true);
if (! is_string($secretKey) || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    $fail('PLUGIN_RELEASE_PRIVATE_KEY must be a base64 Ed25519 secret key supplied by the trusted release environment.');
}

$manifestPath = $pluginRoot.DIRECTORY_SEPARATOR.'plugin.json';
if (! is_file($manifestPath) || is_link($manifestPath)) {
    $fail('The plugin directory must contain a regular plugin.json manifest.');
}
$manifest = json_decode((string) file_get_contents($manifestPath), true, 64, JSON_THROW_ON_ERROR);
if (! is_array($manifest) || ! isset($manifest['key'], $manifest['version'])) {
    $fail('The plugin manifest is invalid.');
}

if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0700, true) && ! is_dir($outputDirectory)) {
    $fail('Unable to create the private release output directory.');
}
$outputDirectory = realpath($outputDirectory);
if ($outputDirectory === false || str_starts_with($outputDirectory, $pluginRoot.DIRECTORY_SEPARATOR)) {
    $fail('Release output must be outside the plugin source directory.');
}

$filename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', (string) $manifest['key'].'-'.$manifest['version']).'.zip';
$archivePath = $outputDirectory.DIRECTORY_SEPARATOR.$filename;
$zip = new ZipArchive();
if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    $fail('Unable to create the plugin release archive.');
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
$files = [];
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if ($file->isLink() || ! $file->isFile()) {
        $zip->close();
        @unlink($archivePath);
        $fail('Symlinks and non-regular files are forbidden in plugin releases.');
    }
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($pluginRoot) + 1));
    $segments = explode('/', $relative);
    if (in_array('.aistudio', $segments, true)
        || in_array('.git', $segments, true)
        || in_array('node_modules', $segments, true)
        || in_array('vendor', $segments, true)
        || in_array('storage', $segments, true)
        || strtolower($relative) === '.env'
        || str_contains(strtolower($relative), '/.env')) {
        $zip->close();
        @unlink($archivePath);
        $fail('The release contains a forbidden development, environment or generated path: '.$relative);
    }
    $files[$relative] = $path;
}
ksort($files, SORT_STRING);
foreach ($files as $relative => $path) {
    if (! $zip->addFile($path, $relative)) {
        $zip->close();
        @unlink($archivePath);
        $fail('Unable to add plugin file to release: '.$relative);
    }
}
if (! $zip->close()) {
    @unlink($archivePath);
    $fail('Unable to finalize the plugin release archive.');
}

$checksum = hash_file('sha256', $archivePath);
if (! is_string($checksum)) {
    @unlink($archivePath);
    $fail('Unable to calculate the release checksum.');
}

$sortKeys = static function (array $value) use (&$sortKeys): array {
    if (array_is_list($value)) {
        return array_map(static fn ($item) => is_array($item) ? $sortKeys($item) : $item, $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        if (is_array($item)) $value[$key] = $sortKeys($item);
    }
    return $value;
};
$payload = json_encode([
    'manifest' => $sortKeys($manifest),
    'package_sha256' => $checksum,
    'schema' => 'aloms-plugin-release-v1',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

$signature = base64_encode(sodium_crypto_sign_detached($payload, $secretKey));
$publicKey = base64_encode(sodium_crypto_sign_publickey_from_secretkey($secretKey));
$manifestOutput = $outputDirectory.DIRECTORY_SEPARATOR.$filename.'.manifest.json';
$signatureOutput = $outputDirectory.DIRECTORY_SEPARATOR.$filename.'.signature.txt';
$checksumOutput = $outputDirectory.DIRECTORY_SEPARATOR.$filename.'.sha256';

file_put_contents($manifestOutput, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
file_put_contents($signatureOutput, $signature.PHP_EOL, LOCK_EX);
file_put_contents($checksumOutput, $checksum.'  '.$filename.PHP_EOL, LOCK_EX);
sodium_memzero($secretKey);

echo json_encode([
    'key_id' => $keyId,
    'archive' => $archivePath,
    'manifest' => $manifestOutput,
    'signature' => $signatureOutput,
    'sha256' => $checksum,
    'trusted_public_key_base64' => $publicKey,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
