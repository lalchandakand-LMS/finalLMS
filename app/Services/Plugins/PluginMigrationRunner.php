<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PluginMigrationRunner
{
    public function __construct(private readonly PluginIntegrityVerifier $integrityVerifier) {}

    public function migrate(PluginInstallation $plugin): void
    {
        $this->integrityVerifier->verify($plugin);
        $root = $this->migrationRoot($plugin);
        if ($root === null) return;

        foreach ($this->migrationFiles($root) as $file) {
            $name = basename($file);
            $identity = [
                'plugin_key' => $plugin->plugin_key,
                'plugin_version' => $plugin->version,
                'migration' => $name,
            ];

            // Claim a new migration atomically. The unique identity index ensures
            // only one concurrent installer can create the running record.
            $claimed = DB::table('plugin_migrations')->insertOrIgnore($identity + [
                'status' => 'running',
                'started_at' => now(),
                'completed_at' => null,
                'error_message' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            if (! $claimed) {
                $existing = DB::table('plugin_migrations')->where($identity)->first();

                if ($existing?->status === 'completed') {
                    continue;
                }

                // A failed migration may have partially committed DDL, and a
                // running row may represent a process that crashed mid-migration.
                // Never execute either automatically; require an operator to
                // inspect/reconcile the database before explicitly recovering it.
                if (in_array($existing?->status, ['failed', 'running'], true)) {
                    throw new RuntimeException(
                        'A previous plugin migration attempt did not complete. Manual database recovery is required before retrying.'
                    );
                }

                if ($existing?->status !== 'rolled_back'
                    || DB::table('plugin_migrations')->where($identity)
                        ->where('status', 'rolled_back')
                        ->update([
                            'status' => 'running',
                            'started_at' => now(),
                            'completed_at' => null,
                            'error_message' => null,
                            'updated_at' => now(),
                        ]) !== 1) {
                    throw new RuntimeException(
                        'The plugin migration is already being processed or is in an unsupported state.'
                    );
                }
            }

            try {
                $migration = require $file;
                if (! $migration instanceof Migration) {
                    throw new RuntimeException('A plugin migration must return a Laravel Migration instance.');
                }
                $migration->up();
                DB::table('plugin_migrations')->where([
                    'plugin_key' => $plugin->plugin_key,
                    'plugin_version' => $plugin->version,
                    'migration' => $name,
                ])->update(['status' => 'completed', 'completed_at' => now(), 'error_message' => null, 'updated_at' => now()]);
            } catch (Throwable $exception) {
                DB::table('plugin_migrations')->where([
                    'plugin_key' => $plugin->plugin_key,
                    'plugin_version' => $plugin->version,
                    'migration' => $name,
                ])->update(['status' => 'failed', 'error_message' => 'Migration failed; inspect server logs.', 'updated_at' => now()]);
                throw new RuntimeException('A signed plugin migration failed. The plugin remains inactive.', previous: $exception);
            }
        }
    }

    public function rollback(PluginInstallation $plugin): void
    {
        if (! in_array($plugin->status, ['installed', 'inactive', 'failed'], true)) {
            throw new RuntimeException('Deactivate the plugin before rolling back its migrations.');
        }
        $this->integrityVerifier->verify($plugin);

        // A failed migration may have partially committed DDL. Even when the
        // plugin itself is in a failed health state, never roll back other
        // completed migrations around an unresolved failed/running migration.
        if ($plugin->status === 'failed' && DB::table('plugin_migrations')
            ->where('plugin_key', $plugin->plugin_key)
            ->where('plugin_version', $plugin->version)
            ->whereIn('status', ['failed', 'running'])
            ->exists()) {
            throw new RuntimeException(
                'A failed or interrupted migration requires manual database recovery before version restore.'
            );
        }
        $root = $this->migrationRoot($plugin);
        if ($root === null) return;

        $identity = [
            'plugin_key' => $plugin->plugin_key,
            'plugin_version' => $plugin->version,
        ];

        // A prior rollback may have partially committed DDL. Do not replay it
        // automatically after a process crash or a failed down() method.
        $interrupted = DB::table('plugin_migrations')->where($identity)
            ->whereIn('status', ['rolling_back', 'rollback_failed'])->exists();
        if ($interrupted) {
            throw new RuntimeException(
                'A previous plugin migration rollback did not complete. Manual database recovery is required before retrying.'
            );
        }

        $records = DB::table('plugin_migrations')->where($identity)
            ->where('status', 'completed')->orderByDesc('id')->get();

        foreach ($records as $record) {
            if (! is_string($record->migration)
                || preg_match('/^[A-Za-z0-9_-]{1,180}\\.php$/D', $record->migration) !== 1) {
                throw new RuntimeException('A recorded plugin migration name is unsafe; rollback was stopped.');
            }

            $claimed = DB::table('plugin_migrations')->where('id', $record->id)
                ->where('status', 'completed')
                ->update(['status' => 'rolling_back', 'updated_at' => now()]);
            if ($claimed !== 1) {
                throw new RuntimeException('The plugin migration rollback is already being processed.');
            }

            try {
                $file = $root.DIRECTORY_SEPARATOR.$record->migration;
                $realFile = realpath($file);
                if (! is_file($file) || is_link($file) || $realFile === false
                    || ! str_starts_with($realFile, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('A recorded plugin migration file is missing or unsafe; rollback was stopped.');
                }
                $migration = require $file;
                if (! $migration instanceof Migration) {
                    throw new RuntimeException('A plugin rollback file did not return a Laravel Migration instance.');
                }
                $migration->down();
                DB::table('plugin_migrations')->where('id', $record->id)->where('status', 'rolling_back')->update([
                    'status' => 'rolled_back', 'updated_at' => now(),
                ]);
            } catch (Throwable $exception) {
                DB::table('plugin_migrations')->where('id', $record->id)->where('status', 'rolling_back')->update([
                    'status' => 'rollback_failed',
                    'error_message' => 'Migration rollback failed; inspect server logs and database state.',
                    'updated_at' => now(),
                ]);
                throw new RuntimeException(
                    'A plugin migration rollback failed. Manual database recovery is required before retrying.',
                    previous: $exception,
                );
            }
        }
    }

    private function migrationRoot(PluginInstallation $plugin): ?string
    {
        $relative = $plugin->manifest['migrations'] ?? null;
        if (! is_string($relative) || $relative === '') return null;
        if (str_contains($relative, '\\') || str_contains($relative, ':') || str_starts_with($relative, '/')
            || in_array('..', explode('/', $relative), true)) {
            throw new RuntimeException('The plugin migration directory is unsafe.');
        }

        $pluginsRoot = realpath(base_path('plugins'));
        $package = base_path((string) $plugin->package_path);
        if ($pluginsRoot === false || is_link(base_path('plugins')) || is_link($package)) {
            throw new RuntimeException('The private plugin directory is unsafe.');
        }
        $packageRoot = realpath($package);
        if ($packageRoot === false || ! str_starts_with($packageRoot, rtrim($pluginsRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The plugin package resolves outside the private plugins directory.');
        }

        $candidate = $packageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_link($candidate) || ! is_dir($candidate)) {
            throw new RuntimeException('The signed manifest declares a plugin migration directory that is missing or unsafe.');
        }
        $real = realpath($candidate);
        if ($real === false || ! str_starts_with($real, rtrim($packageRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The plugin migration directory resolves outside its package.');
        }
        return $real;
    }

    private function migrationFiles(string $root): array
    {
        $files = glob($root.DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            if (is_link($file) || ! is_file($file) || preg_match('/^[A-Za-z0-9_-]{1,180}\.php$/D', basename($file)) !== 1) {
                throw new RuntimeException('A plugin migration file has an unsafe name or file type.');
            }
        }
        return $files;
    }
}
