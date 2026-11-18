<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginMigrationRunner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PluginMigrationRunnerTest extends TestCase
{
    use RefreshDatabase;

    private string $relative = 'plugins/test/migration-check/1.0.0';
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = base_path($this->relative);
        File::ensureDirectoryExists($this->root.'/migrations');
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root) && ! is_link($this->root)) File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_runs_signed_package_migrations_once_and_can_rollback_explicitly(): void
    {
        $file = $this->root.'/migrations/2026_11_15_000001_create_plugin_probe.php';
        file_put_contents($file, <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        \Illuminate\Support\Facades\Schema::create('plugin_migration_probe', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('label');
        });
    }
    public function down(): void {
        \Illuminate\Support\Facades\Schema::dropIfExists('plugin_migration_probe');
    }
};
PHP);

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.migration-check',
            'name' => 'Migration Check',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'installed',
            'manifest' => ['migrations' => 'migrations'],
            'package_path' => $this->relative,
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => ['migrations/2026_11_15_000001_create_plugin_probe.php' => hash_file('sha256', $file)],
            ],
        ])->save();

        $runner = app(PluginMigrationRunner::class);
        $runner->migrate($plugin);
        $this->assertTrue(Schema::hasTable('plugin_migration_probe'));
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'test.migration-check',
            'migration' => '2026_11_15_000001_create_plugin_probe.php',
            'status' => 'completed',
        ]);

        $runner->migrate($plugin);
        $this->assertSame(1, \DB::table('plugin_migrations')->where('plugin_key', 'test.migration-check')->count());

        $runner->rollback($plugin);
        $this->assertFalse(Schema::hasTable('plugin_migration_probe'));
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'test.migration-check',
            'status' => 'rolled_back',
        ]);
    }
    public function test_interrupted_running_claim_blocks_a_second_migration_execution(): void
    {
        $file = $this->root.'/migrations/2026_11_15_000002_running_claim.php';
        file_put_contents($file, <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        \Illuminate\Support\Facades\Schema::create('plugin_running_claim_probe', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
        });
    }
    public function down(): void {
        \Illuminate\Support\Facades\Schema::dropIfExists('plugin_running_claim_probe');
    }
};
PHP);

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.migration-check',
            'name' => 'Running Claim',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'installed',
            'manifest' => ['migrations' => 'migrations'],
            'package_path' => $this->relative,
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => ['migrations/2026_11_15_000002_running_claim.php' => hash_file('sha256', $file)],
            ],
        ])->save();

        \DB::table('plugin_migrations')->insert([
            'plugin_key' => $plugin->plugin_key,
            'plugin_version' => $plugin->version,
            'migration' => basename($file),
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(Schema::hasTable('plugin_running_claim_probe'));
        try {
            app(PluginMigrationRunner::class)->migrate($plugin);
            $this->fail('An interrupted migration claim must not be executed a second time.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('manual recovery', strtolower($exception->getMessage()));
        }
        $this->assertFalse(Schema::hasTable('plugin_running_claim_probe'));
    }


    public function test_an_in_flight_migration_blocks_claiming_a_different_migration_for_the_same_plugin_version(): void
    {
        $runningFile = $this->root.'/migrations/2026_11_15_000001_existing_running.php';
        $nextFile = $this->root.'/migrations/2026_11_15_000002_next_migration.php';

        file_put_contents($runningFile, <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        \Illuminate\Support\Facades\Schema::create('plugin_existing_running_probe', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
        });
    }
    public function down(): void {
        \Illuminate\Support\Facades\Schema::dropIfExists('plugin_existing_running_probe');
    }
};
PHP);
        file_put_contents($nextFile, <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        \Illuminate\Support\Facades\Schema::create('plugin_next_migration_probe', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
        });
    }
    public function down(): void {
        \Illuminate\Support\Facades\Schema::dropIfExists('plugin_next_migration_probe');
    }
};
PHP);

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.migration-check',
            'name' => 'Concurrent Migration Guard',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'installed',
            'manifest' => ['migrations' => 'migrations'],
            'package_path' => $this->relative,
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => [
                    'migrations/'.basename($runningFile) => hash_file('sha256', $runningFile),
                    'migrations/'.basename($nextFile) => hash_file('sha256', $nextFile),
                ],
            ],
        ])->save();

        \DB::table('plugin_migrations')->insert([
            'plugin_key' => $plugin->plugin_key,
            'plugin_version' => $plugin->version,
            'migration' => basename($runningFile),
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(PluginMigrationRunner::class)->migrate($plugin);
            $this->fail('A running migration must block a different migration from the same plugin version.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('another plugin migration is running', strtolower($exception->getMessage()));
        }

        $this->assertFalse(Schema::hasTable('plugin_existing_running_probe'));
        $this->assertFalse(Schema::hasTable('plugin_next_migration_probe'));
        $this->assertDatabaseMissing('plugin_migrations', [
            'plugin_key' => $plugin->plugin_key,
            'migration' => basename($nextFile),
        ]);
    }

    public function test_failed_down_migration_is_marked_and_never_replayed_automatically(): void
    {
        $file = $this->root.'/migrations/2026_11_15_000003_failing_rollback.php';
        file_put_contents($file, <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        \Illuminate\Support\Facades\Schema::create('plugin_rollback_failure_probe', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
        });
    }
    public function down(): void {
        throw new \RuntimeException('simulated partial rollback failure');
    }
};
PHP);

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.migration-check',
            'name' => 'Rollback Failure',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'installed',
            'manifest' => ['migrations' => 'migrations'],
            'package_path' => $this->relative,
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => ['migrations/2026_11_15_000003_failing_rollback.php' => hash_file('sha256', $file)],
            ],
        ])->save();

        $runner = app(PluginMigrationRunner::class);
        $runner->migrate($plugin);
        $this->assertTrue(Schema::hasTable('plugin_rollback_failure_probe'));

        try {
            $runner->rollback($plugin);
            $this->fail('A failing rollback must stop and require manual recovery.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Manual database recovery', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('plugin_rollback_failure_probe'));
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => $plugin->plugin_key,
            'migration' => basename($file),
            'status' => 'rollback_failed',
        ]);

        try {
            $runner->rollback($plugin);
            $this->fail('A failed rollback must not be replayed automatically.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('manual database recovery', strtolower($exception->getMessage()));
        }
    }

    public function test_rollback_rechecks_plugin_status_after_locking_the_registry_row(): void
    {
        $file = $this->root.'/migrations/2026_11_15_000004_active_rollback_guard.php';
        file_put_contents($file, <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {}
    public function down(): void {
        throw new \RuntimeException('Rollback must not execute for an active plugin.');
    }
};
PHP);

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.migration-check',
            'name' => 'Active Rollback Guard',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'installed',
            'manifest' => ['migrations' => 'migrations'],
            'package_path' => $this->relative,
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => ['migrations/2026_11_15_000004_active_rollback_guard.php' => hash_file('sha256', $file)],
            ],
        ])->save();

        \DB::table('plugin_migrations')->insert([
            'plugin_key' => $plugin->plugin_key,
            'plugin_version' => $plugin->version,
            'migration' => basename($file),
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('plugin_registry')->where('id', $plugin->id)->update(['status' => 'active']);

        try {
            app(PluginMigrationRunner::class)->rollback($plugin);
            $this->fail('Rollback must recheck the locked registry state and reject an active plugin.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Deactivate the plugin', $exception->getMessage());
        }

        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'test.migration-check',
            'migration' => basename($file),
            'status' => 'completed',
        ]);
    }

    public function test_plugin_lifecycle_cannot_change_state_while_a_migration_is_running(): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.migration-check',
            'name' => 'Lifecycle Migration Guard',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'installed',
            'manifest' => [],
            'package_path' => $this->relative,
        ])->save();

        \DB::table('plugin_migrations')->insert([
            'plugin_key' => $plugin->plugin_key,
            'plugin_version' => $plugin->version,
            'migration' => '2026_11_15_000005_in_progress.php',
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(\App\Services\Plugins\PluginLifecycle::class)->transition($plugin->plugin_key, 'uninstalled');
            $this->fail('Lifecycle transitions must not race an in-flight plugin migration.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('migration is recovered', $exception->getMessage());
        }

        $this->assertDatabaseHas('plugin_registry', [
            'id' => $plugin->id,
            'status' => 'installed',
        ]);
    }

    public function test_active_plugin_cannot_execute_migrations(): void
    {
        $file = $this->root.'/migrations/2026_11_15_000006_active_migration_guard.php';
        file_put_contents($file, <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        \Illuminate\Support\Facades\Schema::create('plugin_active_migration_probe', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
        });
    }
    public function down(): void {
        \Illuminate\Support\Facades\Schema::dropIfExists('plugin_active_migration_probe');
    }
};
PHP);

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.migration-check',
            'name' => 'Active Migration Guard',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'active',
            'manifest' => ['migrations' => 'migrations'],
            'package_path' => $this->relative,
            'metadata' => [
                'integrity_version' => 1,
                'installed_file_hashes' => ['migrations/2026_11_15_000006_active_migration_guard.php' => hash_file('sha256', $file)],
            ],
        ])->save();

        try {
            app(PluginMigrationRunner::class)->migrate($plugin);
            $this->fail('An active plugin must not execute schema migrations.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Deactivate or stage', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasTable('plugin_active_migration_probe'));
        $this->assertDatabaseMissing('plugin_migrations', [
            'plugin_key' => 'test.migration-check',
            'migration' => basename($file),
        ]);
    }

    public function test_failed_lifecycle_transition_is_blocked_while_any_migration_is_still_running(): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.migration-check',
            'name' => 'Mixed Migration States',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'installed',
            'manifest' => [],
            'package_path' => $this->relative,
        ])->save();

        \DB::table('plugin_migrations')->insert([
            [
                'plugin_key' => $plugin->plugin_key,
                'plugin_version' => $plugin->version,
                'migration' => '2026_11_15_000007_failed.php',
                'status' => 'failed',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plugin_key' => $plugin->plugin_key,
                'plugin_version' => $plugin->version,
                'migration' => '2026_11_15_000008_still_running.php',
                'status' => 'running',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        try {
            app(\App\Services\Plugins\PluginLifecycle::class)->transition($plugin->plugin_key, 'failed');
            $this->fail('A failed migration must not mask another migration that is still running.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('until the migration is recovered', $exception->getMessage());
        }

        $this->assertDatabaseHas('plugin_installations', [
            'id' => $plugin->id,
            'status' => 'installed',
        ]);
    }

}
