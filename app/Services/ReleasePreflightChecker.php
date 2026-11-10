<?php

namespace App\Services;

use App\Models\SystemUpdateCheck;
use App\Models\SystemUpdatePreparation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReleasePreflightChecker
{
    public function inspect(SystemUpdatePreparation $preparation): array
    {
        $checks = [];
        $add = static function (string $key, string $label, string $status, string $message) use (&$checks): void {
            $checks[] = compact('key', 'label', 'status', 'message');
        };

        $disk = Storage::disk('local');
        $privateRoot = realpath($disk->path(''));
        $publicRoot = realpath(public_path());
        $releaseAbsolute = $preparation->release_path ? $disk->path($preparation->release_path) : '';
        $releaseRoot = $releaseAbsolute !== '' ? realpath($releaseAbsolute) : false;

        $isPrivateRelease = $privateRoot !== false
            && $releaseRoot !== false
            && ! is_link($releaseAbsolute)
            && str_starts_with($releaseRoot, rtrim($privateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

        $add(
            'release_state',
            'Prepared release',
            $preparation->status === 'staged' && $preparation->expires_at?->isFuture() && $isPrivateRelease ? 'pass' : 'fail',
            $preparation->status === 'staged' && $isPrivateRelease
                ? 'A current extracted release exists in private storage.'
                : 'A current, non-expired private staged release is required.'
        );

        if ($isPrivateRelease) {
            $versionFile = $releaseRoot.DIRECTORY_SEPARATOR.'VERSION';
            $versionContents = is_file($versionFile) ? file_get_contents($versionFile) : false;
            $versionMatches = is_string($versionContents) && trim($versionContents) === $preparation->version;
            $requiredFilesExist = collect(['artisan', 'public/index.php', 'vendor/autoload.php'])
                ->every(fn (string $file) => is_file($releaseRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file)));

            $add(
                'release_files',
                'Release files',
                $versionMatches && $requiredFilesExist ? 'pass' : 'fail',
                $versionMatches && $requiredFilesExist
                    ? 'Required Laravel entry points and the release version marker are present.'
                    : 'The staged release is missing a required file or has a mismatched VERSION marker.'
            );
        } else {
            $add('release_files', 'Release files', 'fail', 'Release files cannot be inspected until a safe private staging directory exists.');
        }

        $packageAbsolute = $disk->path($preparation->package_path);
        $packageRealPath = realpath($packageAbsolute);
        $packageHash = $packageRealPath && is_file($packageRealPath) ? hash_file('sha256', $packageRealPath) : false;
        $packageMatches = is_string($packageHash) && hash_equals($preparation->package_sha256, $packageHash);
        $add(
            'package_integrity',
            'Package integrity',
            $packageMatches ? 'pass' : 'fail',
            $packageMatches ? 'The original package still matches its verified SHA-256.' : 'The original verified package is missing or its SHA-256 has changed.'
        );

        $phpMinimum = '8.3.0';
        $laravelMajor = 12;
        $requiredExtensions = ['openssl', 'pdo_mysql', 'zip'];
        $manifestCheck = SystemUpdateCheck::query()
            ->where('target_version', $preparation->version)
            ->whereNotNull('details')
            ->latest('checked_at')
            ->first();

        if (is_array($manifestCheck?->details)) {
            $phpMinimum = (string) ($manifestCheck->details['minimum_php'] ?? $phpMinimum);
            $laravelMajor = (int) ($manifestCheck->details['laravel_major'] ?? $laravelMajor);
            $requiredExtensions = array_values(array_unique(array_merge(
                $requiredExtensions,
                array_filter($manifestCheck->details['required_extensions'] ?? [], 'is_string')
            )));
        }

        $phpCompatible = version_compare(PHP_VERSION, $phpMinimum, '>=');
        $add(
            'php_version',
            'PHP version',
            $phpCompatible ? 'pass' : 'fail',
            'Runtime PHP '.PHP_VERSION.'; release requires '.$phpMinimum.' or newer.'
        );

        $actualLaravelMajor = (int) explode('.', app()->version())[0];
        $laravelCompatible = $actualLaravelMajor === $laravelMajor;
        $add(
            'laravel_version',
            'Laravel version',
            $laravelCompatible ? 'pass' : 'fail',
            'Current Laravel major version is '.$actualLaravelMajor.'; release requires '.$laravelMajor.'.'
        );

        $missingExtensions = array_values(array_filter(
            $requiredExtensions,
            fn (string $extension) => ! extension_loaded($extension)
        ));
        $add(
            'php_extensions',
            'Required PHP extensions',
            $missingExtensions === [] ? 'pass' : 'fail',
            $missingExtensions === []
                ? 'All required PHP extensions are loaded: '.implode(', ', $requiredExtensions).'.'
                : 'Missing PHP extensions: '.implode(', ', $missingExtensions).'.'
        );

        $keyPath = (string) config('services.lms_updates.public_key_path', '');
        if ($keyPath !== '' && ! str_starts_with($keyPath, DIRECTORY_SEPARATOR)) {
            $keyPath = base_path($keyPath);
        }
        $realKeyPath = $keyPath !== '' ? realpath($keyPath) : false;
        $keyOutsidePublic = $realKeyPath !== false
            && $publicRoot !== false
            && ! str_starts_with($realKeyPath, rtrim($publicRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
        $keyReady = (bool) config('services.lms_updates.enabled', false)
            && $realKeyPath !== false
            && is_file($realKeyPath)
            && $keyOutsidePublic;
        $add(
            'release_key',
            'Release verification key',
            $keyReady ? 'pass' : 'warn',
            $keyReady
                ? 'Signed update verification is enabled and the public key is outside the public web root.'
                : 'Signed release verification is not fully configured. Keep code activation disabled.'
        );

        $privateOutsidePublic = $privateRoot !== false
            && $publicRoot !== false
            && ! str_starts_with($privateRoot, rtrim($publicRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
        $add(
            'private_storage',
            'Private storage boundary',
            $privateOutsidePublic ? 'pass' : 'fail',
            $privateOutsidePublic
                ? 'Laravel private storage resolves outside the public document root.'
                : 'Private update storage is missing or resolves inside the public document root.'
        );

        $writablePaths = [
            'storage' => storage_path(),
            'private storage' => $disk->path(''),
            'bootstrap cache' => base_path('bootstrap/cache'),
        ];
        $unwritable = array_keys(array_filter($writablePaths, fn (string $path) => ! is_dir($path) || ! is_writable($path)));
        $add(
            'runtime_permissions',
            'Runtime directory permissions',
            $unwritable === [] ? 'pass' : 'fail',
            $unwritable === [] ? 'Storage and bootstrap cache directories are writable.' : 'Not writable: '.implode(', ', $unwritable).'.'
        );

        $codeWritable = is_writable(base_path()) && is_writable(public_path());
        $add(
            'application_permissions',
            'Application directory permissions',
            $codeWritable ? 'pass' : 'warn',
            $codeWritable
                ? 'The current PHP process can write to the application and public directories; activation still requires a tested cPanel strategy.'
                : 'The current PHP process cannot write to every application directory. Verify the cPanel release activation method on staging.'
        );

        $debugDisabled = ! (bool) config('app.debug', false);
        $add(
            'debug_mode',
            'Debug mode',
            $debugDisabled ? 'pass' : 'fail',
            $debugDisabled ? 'APP_DEBUG is disabled.' : 'APP_DEBUG must be false before any production update.'
        );

        $appKeyReady = is_string(config('app.key')) && config('app.key') !== '';
        $add(
            'app_key',
            'Application encryption key',
            $appKeyReady ? 'pass' : 'fail',
            $appKeyReady ? 'APP_KEY is configured.' : 'APP_KEY is missing.'
        );

        try {
            DB::select('SELECT 1');
            $databaseConnected = true;
        } catch (\Throwable) {
            $databaseConnected = false;
        }
        $add(
            'database',
            'Database connectivity',
            $databaseConnected ? 'pass' : 'fail',
            $databaseConnected ? 'The configured database connection is responding.' : 'The configured database connection failed its read-only connectivity check.'
        );

        $migrationTableExists = false;
        $pendingMigrations = [];
        if ($databaseConnected) {
            try {
                $migrationTableExists = Schema::hasTable('migrations');
                if ($migrationTableExists && $isPrivateRelease) {
                    $applied = DB::table('migrations')->pluck('migration')->all();
                    $migrationFiles = glob($releaseRoot.'/database/migrations/*.php') ?: [];
                    foreach ($migrationFiles as $migrationFile) {
                        $migrationName = basename($migrationFile, '.php');
                        if (! in_array($migrationName, $applied, true)) {
                            $pendingMigrations[] = $migrationName;
                        }
                    }
                }
            } catch (\Throwable) {
                $migrationTableExists = false;
            }
        }

        $add(
            'migration_state',
            'Migration preflight',
            ! $migrationTableExists ? 'fail' : ($pendingMigrations === [] ? 'pass' : 'warn'),
            ! $migrationTableExists
                ? 'The current database migration repository could not be read.'
                : ($pendingMigrations === []
                    ? 'No pending migrations were found in the staged release.'
                    : count($pendingMigrations).' pending migration(s) require separate review; this screen will not run them.')
        );

        $freeBytes = $privateRoot !== false ? @disk_free_space($privateRoot) : false;
        $spaceReady = $freeBytes !== false && $freeBytes >= 268435456;
        $add(
            'disk_space',
            'Available disk space',
            $spaceReady ? 'pass' : 'warn',
            $freeBytes === false
                ? 'Free disk space could not be measured; confirm the cPanel account quota manually.'
                : number_format($freeBytes / 1024 / 1024, 1).' MB free on the underlying filesystem. Hosting account quotas may differ.'
        );

        $add(
            'backup',
            'Pre-update backup',
            'warn',
            'A fresh database, media, private-file and current-code backup must be created and verified before activation. Automated update backup orchestration is not implemented yet.'
        );

        $add(
            'hosting_acceptance',
            'cPanel activation capability',
            'warn',
            'Symlink, atomic rename, CLI PHP, cron, file ownership and rollback behavior still require a real staging-subdomain rehearsal.'
        );

        $add(
            'activation',
            'Live activation',
            'warn',
            'Live code activation, migration execution and rollback are intentionally unavailable. This preflight is read-only.'
        );

        $hasFailures = collect($checks)->contains(fn (array $check) => $check['status'] === 'fail');

        return [
            'status' => $hasFailures ? 'blocked' : 'review_required',
            'checks' => $checks,
            'pending_migrations' => $pendingMigrations,
            'activation_available' => false,
            'summary' => $hasFailures
                ? 'One or more required checks failed. Do not proceed with a production update.'
                : 'Technical checks were collected, but manual backup, cPanel staging acceptance and a supported activation/rollback engine are still required.',
        ];
    }
}
