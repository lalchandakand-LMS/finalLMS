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
            $claim = DB::transaction(function () use ($plugin, $name): string {
                $lockedPlugin = PluginInstallation::query()->whereKey($plugin->getKey())->lockForUpdate()->first();
                if (! $lockedPlugin) {
                    throw new RuntimeException('The plugin installation record is missing; migration was stopped.');
                }
                if ($lockedPlugin->version !== $plugin->version
                    || $lockedPlugin->package_path !== $plugin->package_path
                    || $lockedPlugin->package_sha256 !== $plugin->package_sha256) {
                    throw new RuntimeException('The installed plugin package changed while migration was being prepared; reload the package before retrying.');
                }
                if (! in_array($lockedPlugin->status, ['installed', 'inactive', 'update_staged'], true)) {
                    throw new RuntimeException('Deactivate or stage the plugin before running its migrations.');
                }
                $unsettled = DB::table('plugin_migrations')->where([
                    'plugin_key' => $plugin->plugin_key,
                    'plugin_version' => $plugin->version,
                ])->whereIn('status', ['running', 'rolling_back', 'failed', 'rollback_failed'])->exists();
                if ($unsettled) {
                    throw new RuntimeException('Another plugin migration is running or requires manual recovery.');
                }

                $record = DB::table('plugin_migrations')->where([
                    'plugin_key' => $plugin->plugin_key,
                    'plugin_version' => $plugin->version,
                    'migration' => $name,
                ])->lockForUpdate()->first();

                if ($record?->status === 'completed') return 'completed';
                if ($record && $record->status !== 'rolled_back') {
                    throw new RuntimeException('A plugin migration is already running or requires manual recovery.');
                }

                $values = [
                    'status' => 'running',
                    'started_at' => now(),
                    'completed_at' => null,
                    'error_message' => null,
                    'updated_at' => now(),
                ];

                if ($record) {
                    DB::table('plugin_migrations')->where('id', $record->id)->update($values);
                } else {
                    DB::table('plugin_migrations')->insert([
                        'plugin_key' => $plugin->plugin_key,
                        'plugin_version' => $plugin->version,
                        'migration' => $name,
                        ...$values,
                        'created_at' => now(),
                    ]);
                }

                return 'claimed';
            }, 3);

            if ($claim === 'completed') continue;

            try {
                $migration = require $file;
                if (! $migration instanceof Migration) {
                    throw new RuntimeException('A plugin migration must return a Laravel Migration instance.');
                }
                $migration->up();

                $updated = DB::table('plugin_migrations')->where([
                    'plugin_key' => $plugin->plugin_key,
                    'plugin_version' => $plugin->version,
                    'migration' => $name,
                    'status' => 'running',
                ])->update(['status' => 'completed', 'completed_at' => now(), 'error_message' => null, 'updated_at' => now()]);

                if ($updated !== 1) {
                    throw new RuntimeException('The plugin migration claim was lost before completion was recorded.');
                }
            } catch (Throwable $exception) {
                DB::table('plugin_migrations')->where([
                    'plugin_key' => $plugin->plugin_key,
                    'plugin_version' => $plugin->version,
                    'migration' => $name,
                    'status' => 'running',
                ])->update(['status' => 'failed', 'error_message' => 'Migration failed; inspect server logs.', 'updated_at' => now()]);

                throw new RuntimeException('A signed plugin migration failed. The plugin remains inactive.', previous: $exception);
            }
        }
    }

    public function rollback(PluginInstallation $plugin): void
    {
        if (! in_array($plugin->status, ['installed', 'inactive', 'update_staged'], true)) {
            throw new RuntimeException('Deactivate the plugin before rolling back its migrations.');
        }
        $this->integrityVerifier->verify($plugin);

        $unsettled = DB::table('plugin_migrations')->where([
            'plugin_key' => $plugin->plugin_key,
            'plugin_version' => $plugin->version,
        ])->whereIn('status', ['failed', 'running', 'rolling_back', 'rollback_failed'])->exists();

        if ($unsettled) {
            throw new RuntimeException('A failed or interrupted migration requires manual database recovery before package rollback.');
        }

        $root = $this->migrationRoot($plugin);
        if ($root === null) return;

        $records = DB::table('plugin_migrations')->where([
            'plugin_key' => $plugin->plugin_key,
            'plugin_version' => $plugin->version,
            'status' => 'completed',
        ])->orderByDesc('id')->get();

        foreach ($records as $record) {
            if (! is_string($record->migration)
                || preg_match('/^[A-Za-z0-9_-]{1,180}\.php$/D', $record->migration) !== 1) {
                throw new RuntimeException('A recorded plugin migration name is unsafe; rollback was stopped.');
            }

            $file = $root.DIRECTORY_SEPARATOR.$record->migration;
            $realFile = realpath($file);
            if (! is_file($file) || is_link($file) || $realFile === false
                || ! str_starts_with($realFile, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('A recorded plugin migration file is missing or unsafe; rollback was stopped.');
            }

            $claimed = DB::transaction(function () use ($plugin, $record): bool {
                $lockedPlugin = PluginInstallation::query()->whereKey($plugin->getKey())->lockForUpdate()->first();
                if (! $lockedPlugin) {
                    throw new RuntimeException('The plugin installation record is missing; rollback was stopped.');
                }
                if ($lockedPlugin->version !== $plugin->version
                    || $lockedPlugin->package_path !== $plugin->package_path
                    || $lockedPlugin->package_sha256 !== $plugin->package_sha256) {
                    throw new RuntimeException('The installed plugin package changed while rollback was being prepared; reload the package before retrying.');
                }
                if (! in_array($lockedPlugin->status, ['installed', 'inactive', 'update_staged'], true)) {
                    throw new RuntimeException('Deactivate the plugin before rolling back its migrations.');
                }
                $unsettled = DB::table('plugin_migrations')->where([
                    'plugin_key' => $plugin->plugin_key,
                    'plugin_version' => $plugin->version,
                ])->whereIn('status', ['running', 'rolling_back', 'failed', 'rollback_failed'])->exists();
                if ($unsettled) {
                    throw new RuntimeException('Another plugin migration is running or requires manual recovery.');
                }

                $current = DB::table('plugin_migrations')->where('id', $record->id)->lockForUpdate()->first();
                if (! $current || $current->status !== 'completed') return false;

                DB::table('plugin_migrations')->where('id', $record->id)->update([
                    'status' => 'rolling_back',
                    'updated_at' => now(),
                ]);

                return true;
            }, 3);

            if (! $claimed) {
                throw new RuntimeException('The plugin migration state changed before rollback could be claimed.');
            }

            try {
                $migration = require $file;
                if (! $migration instanceof Migration) {
                    throw new RuntimeException('A plugin rollback file did not return a Laravel Migration instance.');
                }
                $migration->down();

                DB::table('plugin_migrations')->where('id', $record->id)->where('status', 'rolling_back')->update([
                    'status' => 'rolled_back',
                    'updated_at' => now(),
                ]);
            } catch (Throwable $exception) {
                DB::table('plugin_migrations')->where('id', $record->id)->where('status', 'rolling_back')->update([
                    'status' => 'rollback_failed',
                    'error_message' => 'Rollback failed; inspect server logs and recover the database manually.',
                    'updated_at' => now(),
                ]);

                throw new RuntimeException('A plugin migration rollback failed. Manual database recovery is required.', previous: $exception);
            }
        }
    }

    /**
     * Return true while any declared migration has not completed successfully.
     * Lifecycle activation uses this to close the gap between sequential migration claims.
     */
    public function hasPendingMigrations(PluginInstallation $plugin): bool
    {
        $root = $this->migrationRoot($plugin);
        if ($root === null) {
            return false;
        }

        foreach ($this->migrationFiles($root) as $file) {
            $completed = DB::table('plugin_migrations')
                ->where('plugin_key', $plugin->plugin_key)
                ->where('plugin_version', $plugin->version)
                ->where('migration', basename($file))
                ->where('status', 'completed')
                ->exists();

            if (! $completed) {
                return true;
            }
        }

        return false;
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
