<?php

namespace App\Services;

use App\Models\SystemUpdatePreparation;
use App\Models\SystemUpdatePreflight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ReleaseActivationPreflight
{
    public function run(SystemUpdatePreparation $preparation, User $admin): SystemUpdatePreflight
    {
        $preparation->refresh();
        if ($preparation->status !== 'staged'
            || ! $preparation->expires_at
            || $preparation->expires_at->isPast()
            || ! is_string($preparation->release_path)
            || $preparation->release_path === '') {
            throw new RuntimeException('Activation preflight requires a current, extracted release in private staging.');
        }

        $checks = [];
        $disk = Storage::disk('local');
        $privateRoot = realpath($disk->path(''));
        $releasePath = $disk->path($preparation->release_path);
        $releaseRoot = realpath($releasePath);

        $this->add($checks, 'staged_release', $privateRoot !== false
            && $releaseRoot !== false
            && ! is_link($releasePath)
            && is_dir($releaseRoot)
            && str_starts_with($releaseRoot, rtrim($privateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR),
            'The extracted release must resolve to a real directory inside private Laravel storage.');

        if ($releaseRoot !== false && is_dir($releaseRoot)) {
            foreach (['artisan', 'public/index.php', 'vendor/autoload.php', 'VERSION'] as $relative) {
                $file = $releaseRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $this->add($checks, 'release_file:'.$relative, is_file($file) && ! is_link($file),
                    'Required staged application file: '.$relative);
            }

            $version = @file_get_contents($releaseRoot.DIRECTORY_SEPARATOR.'VERSION');
            $this->add($checks, 'release_version', is_string($version) && trim($version) === $preparation->version,
                'The staged VERSION marker must match the verified release version.');
        }

        $baseRoot = realpath(base_path());
        $publicRoot = realpath(public_path());
        $this->add($checks, 'application_root', $baseRoot !== false && is_dir($baseRoot),
            'The current Laravel application root must be resolvable.');
        if ($baseRoot !== false) {
            $this->add($checks, 'application_root_writable', is_writable($baseRoot),
                'The current application root must be writable by the PHP process for a future cPanel release swap.');
        }
        $this->add($checks, 'public_root', $publicRoot !== false && is_dir($publicRoot),
            'The current public document root must be resolvable.');
        if ($publicRoot !== false) {
            $this->add($checks, 'public_root_writable', is_writable($publicRoot),
                'The public document root must be writable by the PHP process for a future controlled release swap.');
        }

        if ($baseRoot !== false && $publicRoot !== false) {
            $publicInsideApp = str_starts_with($publicRoot, rtrim($baseRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
            $this->add($checks, 'document_root_layout', $publicInsideApp,
                $publicInsideApp
                    ? 'The public directory is inside the Laravel application root.'
                    : 'The document root is outside the Laravel root; cPanel path mapping needs a manual deployment review.',
                $publicInsideApp ? 'pass' : 'warning');
        }

        $environmentFile = base_path('.env');
        $environmentReal = realpath($environmentFile);
        $this->add($checks, 'environment_file', $environmentReal !== false
            && is_file($environmentReal)
            && ($publicRoot === false || ! str_starts_with($environmentReal, rtrim($publicRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)),
            'The private environment file must exist and remain outside the public document root.');

        foreach ([
            'storage' => storage_path(),
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ] as $key => $path) {
            $this->add($checks, 'writable:'.$key, is_dir($path) && is_writable($path),
                'Required writable directory: '.$key);
        }

        $this->add($checks, 'php_version', version_compare(PHP_VERSION, '8.3.0', '>='),
            'The web PHP runtime must be PHP 8.3 or newer.');
        foreach (['openssl', 'zip', 'pdo_mysql'] as $extension) {
            $this->add($checks, 'extension:'.$extension, extension_loaded($extension),
                'Required PHP extension: '.$extension);
        }

        try {
            DB::connection()->select('SELECT 1');
            $this->add($checks, 'database_connection', true, 'The configured database connection responds to a read-only health query.');
        } catch (Throwable) {
            $this->add($checks, 'database_connection', false, 'The configured database connection failed its read-only health query.');
        }

        try {
            $driver = DB::connection()->getDriverName();
            $this->add($checks, 'database_driver', $driver === 'mysql',
                $driver === 'mysql'
                    ? 'The configured database driver is MySQL.'
                    : 'The production LMS update workflow requires MySQL/MariaDB; the configured database driver is '.$driver.'.');
        } catch (Throwable) {
            $this->add($checks, 'database_driver', false, 'The configured database driver could not be determined.');
        }

        try {
            if (Schema::hasTable('migrations')) {
                $ran = array_fill_keys(DB::table('migrations')->pluck('migration')->all(), true);
                $migrationDirectory = $releaseRoot !== false ? $releaseRoot.'/database/migrations' : '';
                $files = $migrationDirectory !== '' && is_dir($migrationDirectory)
                    ? glob($migrationDirectory.'/*.php')
                    : [];
                $pending = [];
                foreach ($files ?: [] as $file) {
                    $name = pathinfo($file, PATHINFO_FILENAME);
                    if (! isset($ran[$name])) {
                        $pending[] = $name;
                    }
                }
                sort($pending, SORT_STRING);
                $this->add($checks, 'pending_migrations', $pending === [],
                    $pending === []
                        ? 'The staged release contains no migrations absent from the current migrations table.'
                        : 'The staged release contains pending migrations that require independent review before any activation.',
                    $pending === [] ? 'pass' : 'warning',
                    ['count' => count($pending), 'names' => array_slice($pending, 0, 100)]);
            } else {
                $this->add($checks, 'pending_migrations', false, 'The current database does not have a Laravel migrations table.');
            }
        } catch (Throwable) {
            $this->add($checks, 'pending_migrations', false, 'Pending migration status could not be read safely.');
        }

        if ($baseRoot !== false && $privateRoot !== false) {
            $baseStat = @stat($baseRoot);
            $privateStat = @stat($privateRoot);
            $sameDevice = is_array($baseStat) && is_array($privateStat)
                && isset($baseStat['dev'], $privateStat['dev'])
                && $baseStat['dev'] === $privateStat['dev'];
            $this->add($checks, 'same_filesystem', $sameDevice,
                $sameDevice
                    ? 'Application and private staging paths are on the same filesystem.'
                    : 'Application and private staging paths are not confirmed on the same filesystem; atomic rename is not available.',
                $sameDevice ? 'pass' : 'warning');
        }

        $freeBytes = $privateRoot !== false ? @disk_free_space($privateRoot) : false;
        $requiredBytes = max(268435456, (int) $preparation->file_size * 2);
        $enoughDisk = is_float($freeBytes) || is_int($freeBytes)
            ? $freeBytes >= $requiredBytes
            : false;
        $this->add($checks, 'disk_space', $enoughDisk,
            $enoughDisk
                ? 'Private storage has the minimum free space for a future backup and activation rehearsal.'
                : 'Free disk space is unavailable or below the conservative pre-activation threshold.',
            $enoughDisk ? 'pass' : 'warning',
            ['required_bytes' => $requiredBytes, 'free_bytes' => is_numeric($freeBytes) ? (int) $freeBytes : null]);

        $this->add($checks, 'symlink_support', false,
            function_exists('symlink')
                ? 'PHP exposes the symlink function, but actual cPanel filesystem policy has not been tested.'
                : 'PHP symlink support is unavailable; an explicitly tested directory-swap strategy is required.',
            'warning');

        // The current backup module covers database and selected media, not a complete code-release snapshot.
        $this->add($checks, 'verified_pre_activation_backup', false,
            'Activation is blocked: a complete code snapshot plus a verified database/media backup must be created and tied to this update before code can be switched.');

        $blocked = collect($checks)->contains(fn (array $check) => $check['status'] === 'blocked');
        $warning = collect($checks)->contains(fn (array $check) => $check['status'] === 'warning');
        $status = $blocked ? 'blocked' : ($warning ? 'warning' : 'ready');
        $summary = match ($status) {
            'ready' => 'Preflight checks passed. This report does not authorize or perform activation.',
            'warning' => 'Preflight found warnings that require review. Activation remains disabled.',
            default => 'Preflight found blocking requirements. No application files or database records were changed.',
        };

        return SystemUpdatePreflight::create([
            'preparation_id' => $preparation->id,
            'performed_by' => $admin->id,
            'status' => $status,
            'checks' => $checks,
            'summary' => $summary,
            'checked_at' => now(),
        ]);
    }

    private function add(
        array &$checks,
        string $key,
        bool $passed,
        string $message,
        string $failureStatus = 'blocked',
        array $details = []
    ): void {
        $checks[] = [
            'key' => $key,
            'status' => $passed ? 'pass' : $failureStatus,
            'message' => $message,
            'details' => $details,
        ];
    }
}
