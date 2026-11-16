<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$expectedVersion = getenv('EXPECTED_VERSION');
$installedVersion = config('services.lms_updates.current_version');

if (! is_string($expectedVersion)
    || ! preg_match('/^\d+\.\d+\.\d+$/', $expectedVersion)
    || $installedVersion !== $expectedVersion) {
    fwrite(STDERR, "Cached LMS version does not match the immutable release tag.\n");
    exit(1);
}

fwrite(STDOUT, "Verified cached LMS release version: {$installedVersion}\n");
