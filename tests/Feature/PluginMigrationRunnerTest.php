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

    public function test_failed_migration_is_not_automatically_retried_after_possible_partial_ddl(): void
    {
        $name = '2026_11_15_000002_create_partial_plugin_probe.php';
        $file = $this->root.'/migrations/'.$name;
        file_put_contents($file, <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        \Illuminate\Support\Facades\Schema::create('plugin_migration_attempts', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
        });
        \Illuminate\Support\Facades\DB::table('plugin_migration_attempts')->insert([]);
        throw new \RuntimeException('Simulated failure after a DDL statement committed.');
    }
    public function down(): void {
        \Illuminate\Support\Facades\Schema::dropIfExists('plugin_migration_attempts');
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
                'installed_file_hashes' => ['migrations/'.$name => hash_file('sha256', $file)],
            ],
        ])->save();

        $runner = app(PluginMigrationRunner::class);

        try {
            $runner->migrate($plugin);
            $this->fail('The simulated migration failure should be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('migration failed', strtolower($exception->getMessage()));
        }

        $this->assertTrue(Schema::hasTable('plugin_migration_attempts'));
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'test.migration-check',
            'plugin_version' => '1.0.0',
            'migration' => $name,
            'status' => 'failed',
        ]);
        $this->assertSame(1, \DB::table('plugin_migration_attempts')->count());

        try {
            $runner->migrate($plugin);
            $this->fail('A failed migration must require manual recovery, not automatic retry.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('manual database recovery is required', strtolower($exception->getMessage()));
        }

        $this->assertSame(1, \DB::table('plugin_migration_attempts')->count());
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'test.migration-check',
            'plugin_version' => '1.0.0',
            'migration' => $name,
            'status' => 'failed',
        ]);
    }


    public function test_failed_migration_rollback_is_not_automatically_retried_after_partial_ddl(): void
    {
        $name = '2026_11_15_000003_create_partial_rollback_probe.php';
        $file = $this->root.'/migrations/'.$name;
        file_put_contents($file, <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void {
        \Illuminate\Support\Facades\Schema::create('plugin_rollback_attempts', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
        });
    }
    public function down(): void {
        \Illuminate\Support\Facades\Schema::dropIfExists('plugin_rollback_attempts');
        throw new \RuntimeException('Simulated failure after rollback DDL committed.');
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
                'installed_file_hashes' => ['migrations/'.$name => hash_file('sha256', $file)],
            ],
        ])->save();

        $runner = app(PluginMigrationRunner::class);
        $runner->migrate($plugin);
        $this->assertTrue(Schema::hasTable('plugin_rollback_attempts'));

        try {
            $runner->rollback($plugin);
            $this->fail('The simulated partial rollback failure should be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('manual database recovery is required', strtolower($exception->getMessage()));
        }

        $this->assertFalse(Schema::hasTable('plugin_rollback_attempts'));
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'test.migration-check',
            'plugin_version' => '1.0.0',
            'migration' => $name,
            'status' => 'rollback_failed',
        ]);

        try {
            $runner->rollback($plugin);
            $this->fail('A failed rollback must require manual recovery, not automatic retry.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('manual database recovery is required', strtolower($exception->getMessage()));
        }

        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'test.migration-check',
            'plugin_version' => '1.0.0',
            'migration' => $name,
            'status' => 'rollback_failed',
        ]);
    }


    public function test_interrupted_rolling_back_state_requires_manual_recovery(): void
    {
        $name = '2026_11_15_000004_create_interrupted_rollback_probe.php';
        $file = $this->root.'/migrations/'.$name;
        file_put_contents($file, <<<'PHP'
<?php
return new class extends \\Illuminate\\Database\\Migrations\\Migration {
    public function up(): void {
        \\Illuminate\\Support\\Facades\\Schema::create('plugin_interrupted_rollback_probe', function (\\Illuminate\\Database\\Schema\\Blueprint $table): void {
            $table->id();
        });
    }
    public function down(): void {
        \\Illuminate\\Support\\Facades\\Schema::dropIfExists('plugin_interrupted_rollback_probe');
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
                'installed_file_hashes' => ['migrations/'.$name => hash_file('sha256', $file)],
            ],
        ])->save();

        $runner = app(PluginMigrationRunner::class);
        $runner->migrate($plugin);
        \DB::table('plugin_migrations')->where([
            'plugin_key' => 'test.migration-check',
            'plugin_version' => '1.0.0',
            'migration' => $name,
        ])->update(['status' => 'rolling_back']);

        try {
            $runner->rollback($plugin);
            $this->fail('An interrupted rollback must require manual database recovery.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('manual database recovery is required', strtolower($exception->getMessage()));
        }

        $this->assertTrue(Schema::hasTable('plugin_interrupted_rollback_probe'));
        $this->assertDatabaseHas('plugin_migrations', [
            'plugin_key' => 'test.migration-check',
            'plugin_version' => '1.0.0',
            'migration' => $name,
            'status' => 'rolling_back',
        ]);
    }

}
