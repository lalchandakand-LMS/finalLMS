<?php

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
};

if (! extension_loaded('sodium') || ! class_exists(ZipArchive::class)) {
    $fail('The sodium and zip PHP extensions are required.');
}

$pluginInput = $argv[1] ?? '';
$outputInput = $argv[2] ?? '';
if ($pluginInput === '' || is_link($pluginInput)) {
    $fail('The plugin source must be a regular directory, not a symlink.');
}
$pluginRoot = realpath($pluginInput);
$repoRoot = realpath(__DIR__.'/..');
$keyId = trim((string) getenv('PLUGIN_RELEASE_KEY_ID'));
$encodedSecret = (string) getenv('PLUGIN_RELEASE_PRIVATE_KEY');

if ($pluginRoot === false || ! is_dir($pluginRoot) || $repoRoot === false || $outputInput === '') {
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

if (is_link($outputInput)) {
    $fail('The private release output directory cannot be a symlink.');
}
if (! is_dir($outputInput) && ! mkdir($outputInput, 0700, true) && ! is_dir($outputInput)) {
    $fail('Unable to create the private release output directory.');
}
$outputDirectory = realpath($outputInput);
if ($outputDirectory === false
    || $outputDirectory === $pluginRoot
    || str_starts_with($outputDirectory, $pluginRoot.DIRECTORY_SEPARATOR)
    || $outputDirectory === $repoRoot
    || str_starts_with($outputDirectory, $repoRoot.DIRECTORY_SEPARATOR)) {
    $fail('Release output must be outside both the plugin source and repository directories.');
}
$directoryPermissions = fileperms($outputDirectory);
if ($directoryPermissions === false || ($directoryPermissions & 0077) !== 0) {
    $fail('The private release output directory must have mode 0700 (no group or world access).');
}

$publicRoot = realpath($repoRoot.DIRECTORY_SEPARATOR.'public');
$publicStorageRoot = realpath($repoRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public');
foreach ([$publicRoot, $publicStorageRoot] as $publicPath) {
    if ($publicPath !== false && ($outputDirectory === $publicPath || str_starts_with($outputDirectory, $publicPath.DIRECTORY_SEPARATOR))) {
        $fail('Release output cannot be inside a public web or storage directory.');
    }
}

$filename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', (string) $manifest['key'].'-'.$manifest['version']).'.zip';
$archivePath = $outputDirectory.DIRECTORY_SEPARATOR.$filename;
$manifestOutput = $archivePath.'.manifest.json';
$signatureOutput = $archivePath.'.signature.txt';
$checksumOutput = $archivePath.'.sha256';

foreach ([$archivePath, $manifestOutput, $signatureOutput, $checksumOutput] as $outputPath) {
    if (is_link($outputPath) || (file_exists($outputPath) && ! is_file($outputPath))) {
        $fail('Release output paths must be regular files and cannot be symlinks.');
    }
}

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
    $sourcePath = $file->getPathname();
    if ($file->isLink() || ! $file->isFile()) {
        $zip->close();
        @unlink($archivePath);
        $fail('Symlinks and non-regular files are forbidden in plugin releases.');
    }

    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($sourcePath, strlen($pluginRoot) + 1));
    $segments = explode('/', $relative);
    $forbidden = false;
    foreach ($segments as $segment) {
        $lower = strtolower($segment);
        if (in_array($lower, ['.git', 'node_modules', 'vendor', 'storage'], true)
            || $lower === '.env'
            || str_starts_with($lower, '.env.')) {
            $forbidden = true;
            break;
        }
    }
    if ($forbidden) {
        $zip->close();
        @unlink($archivePath);
        $fail('The release contains a forbidden development, environment or generated path: '.$relative);
    }

    $files[$relative] = $sourcePath;
}
ksort($files, SORT_STRING);
foreach ($files as $relative => $sourcePath) {
    if (! $zip->addFile($sourcePath, $relative)) {
        $zip->close();
        @unlink($archivePath);
        $fail('Unable to add plugin file to release: '.$relative);
    }
}
if (! $zip->close()) {
    @unlink($archivePath);
    $fail('Unable to finalize the plugin release archive.');
}
if (! chmod($archivePath, 0600)) {
    @unlink($archivePath);
    $fail('Unable to restrict release archive permissions.');
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
        if (is_array($item)) {
            $value[$key] = $sortKeys($item);
        }
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
sodium_memzero($secretKey);

$manifestContents = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$writes = [
    $manifestOutput => $manifestContents,
    $signatureOutput => $signature.PHP_EOL,
    $checksumOutput => $checksum.'  '.$filename.PHP_EOL,
];
foreach ($writes as $path => $contents) {
    if (file_put_contents($path, $contents, LOCK_EX) === false || ! chmod($path, 0600)) {
        foreach ([$archivePath, $manifestOutput, $signatureOutput, $checksumOutput] as $cleanupPath) {
            if (is_file($cleanupPath) && ! is_link($cleanupPath)) {
                @unlink($cleanupPath);
            }
        }
        $fail('Unable to write private release metadata.');
    }
}

echo json_encode([
    'key_id' => $keyId,
    'archive' => $archivePath,
    'manifest' => $manifestOutput,
    'signature' => $signatureOutput,
    'sha256' => $checksum,
    'trusted_public_key_base64' => $publicKey,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
