<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

/**
 * Refreshes the application schema once, installs the selected first-party
 * plugin migrations before opening the test transaction, then relies on
 * transaction rollback for per-test isolation. This avoids rolling back
 * production migrations whose down() methods intentionally protect real data.
 */
trait RefreshDatabaseWithPluginMigrations
{
    use RefreshDatabase;

    /**
     * @return list<string> Absolute paths to plugin migration directories.
     */
    abstract protected function pluginMigrationPaths(): array;

    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            $this->app[Kernel::class]->setArtisan(null);
            RefreshDatabaseState::$migrated = true;
        }

        foreach ($this->pluginMigrationPaths() as $path) {
            $this->artisan('migrate', [
                '--path' => $path,
                '--realpath' => true,
                '--force' => true,
            ]);
            $this->app[Kernel::class]->setArtisan(null);
        }

        $this->beginDatabaseTransaction();
    }
}
