<?php

namespace Tests\Feature;

use App\Http\Controllers\BackupController;
use App\Models\SystemUpdateBackup;
use App\Models\SystemUpdateCheck;
use App\Models\SystemUpdatePreparation;
use App\Models\SystemUpdatePreflight;
use App\Models\User;
use App\Services\ReleaseManifestChecker;
use App\Services\UpdateBackupCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class AdminUpdateCenterTest extends TestCase
{
    use RefreshDatabase;

    private ?string $publicKeyPath = null;

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('updates');
        Storage::disk('local')->deleteDirectory('backups');

        if ($this->publicKeyPath && is_file($this->publicKeyPath)) {
            @unlink($this->publicKeyPath);
        }

        parent::tearDown();
    }

    public function test_update_preparation_schema_contains_all_private_staging_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('system_update_preparations', [
            'migration_set_sha256',
            'release_path',
            'staged_at',
        ]));
    }

    public function test_admin_can_open_the_system_update_center(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSee('System Updates')
            ->assertSee('Check for updates');
    }

    public function test_non_admin_roles_cannot_open_or_check_system_updates(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        foreach (['student', 'teacher', 'branch_manager', 'accountant', 'guardian'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('admin.updates.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.updates.check'))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.updates.prepare'))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('system_update_checks', 0);
        $this->assertDatabaseCount('system_update_preparations', 0);
    }

    public function test_disabled_update_check_is_recorded_without_downloading_any_package(): void
    {
        config(['services.lms_updates.enabled' => false]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.check'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('system_update_checks', [
            'checked_by' => $admin->id,
            'status' => 'failed',
            'current_version' => config('services.lms_updates.current_version', '0.0.0'),
        ]);
    }

    public function test_admin_check_accepts_a_valid_signed_manifest_and_persists_its_digest(): void
    {
        $this->configureSignedRelease();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.check'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('success');

        $check = SystemUpdateCheck::query()->firstOrFail();
        $this->assertSame('available', $check->status);
        $this->assertSame('1.0.1', $check->target_version);
        $this->assertSame(64, strlen($check->manifest_sha256));
        $this->assertSame(40, strlen($check->git_commit));
        $this->assertSame('1.0.1', $check->details['version']);
    }

    public function test_release_manifest_with_invalid_signature_is_rejected(): void
    {
        $this->configureSignedRelease(signatureOverride: 'not-a-valid-rsa-signature');

        try {
            app(ReleaseManifestChecker::class)->check();
            $this->fail('An invalid release signature must never be accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('signature is invalid', $exception->getMessage());
        }
    }

    public function test_release_manifest_older_than_installed_version_is_rejected(): void
    {
        $this->configureSignedRelease(version: '0.9.9');

        try {
            app(ReleaseManifestChecker::class)->check();
            $this->fail('A release channel must not permit a downgrade.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Downgrades are blocked', $exception->getMessage());
        }
    }

    public function test_release_manifest_redirect_to_untrusted_host_is_rejected(): void
    {
        $this->configureSignedRelease(redirectToUntrustedHost: true);

        try {
            app(ReleaseManifestChecker::class)->check();
            $this->fail('A redirect to an untrusted host must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('untrusted destination', $exception->getMessage());
        }
    }

    public function test_admin_can_stage_a_signed_package_after_hash_and_migration_digest_validation(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'aloms-release-package-');
        $this->assertIsString($zipPath);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::OVERWRITE) === true);

        $migrationPath = 'database/migrations/2026_11_10_130000_test_update.php';
        $migrationContents = "<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration {};\n";
        foreach ([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            $migrationPath => $migrationContents,
            'storage/.gitignore' => '',
            'bootstrap/cache/.gitignore' => '',
        ] as $path => $contents) {
            $this->assertTrue($zip->addFromString($path, $contents));
        }
        $this->assertTrue($zip->close());
        $packageBytes = file_get_contents($zipPath);
        $this->assertIsString($packageBytes);
        @unlink($zipPath);

        $migrationDigest = hash('sha256', hash('sha256', $migrationContents).'  '.$migrationPath."\n");
        $this->configureSignedRelease(packageBytes: $packageBytes, migrationDigest: $migrationDigest);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.prepare'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('success');

        $preparation = SystemUpdatePreparation::query()->firstOrFail();
        $this->assertSame('verified', $preparation->status);
        $this->assertSame(hash('sha256', $packageBytes), $preparation->package_sha256);
        $this->assertSame($migrationDigest, $preparation->migration_set_sha256);
        $this->assertTrue(Storage::disk('local')->exists($preparation->package_path));
    }

    public function test_admin_can_extract_verified_release_into_private_staging_without_installing_it(): void
    {
        $packageBytes = $this->createReleasePackage([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            'storage/.gitignore' => '',
            'bootstrap/cache/.gitignore' => '',
        ]);
        $this->configureSignedRelease(packageBytes: $packageBytes, migrationDigest: hash('sha256', ''));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.prepare'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('success');

        $preparation = SystemUpdatePreparation::query()->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.updates.extract', $preparation))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('success');

        $preparation->refresh();
        $this->assertSame('staged', $preparation->status);
        $this->assertNotNull($preparation->staged_at);
        $this->assertNotNull($preparation->release_path);
        $this->assertTrue(Storage::disk('local')->exists($preparation->release_path.'/VERSION'));
        $this->assertTrue(Storage::disk('local')->exists($preparation->release_path.'/public/index.php'));
        $this->assertFalse(Storage::disk('local')->exists($preparation->release_path.'/.env'));
        $this->assertTrue(Storage::disk('local')->exists($preparation->package_path));
        $this->assertSame(0600, fileperms(Storage::disk('local')->path($preparation->release_path.'/VERSION')) & 0777);
    }

    public function test_admin_cannot_stage_a_package_with_a_mismatched_migration_set_digest(): void
    {
        $migrationPath = 'database/migrations/2026_11_10_140001_test_release_migration.php';
        $packageBytes = $this->createReleasePackage([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            $migrationPath => "<?php return new class {};\n",
            'storage/.gitignore' => '',
            'bootstrap/cache/.gitignore' => '',
        ]);
        $this->configureSignedRelease(
            packageBytes: $packageBytes,
            migrationDigest: str_repeat('0', 64)
        );
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.prepare'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('system_update_preparations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('updates/staging'));
    }

    public function test_admin_can_create_and_verify_code_snapshot_with_recent_database_media_backup(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $packageBytes = $this->createReleasePackage([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            'storage/.gitignore' => '',
            'bootstrap/cache/.gitignore' => '',
        ]);
        $this->configureSignedRelease(packageBytes: $packageBytes, migrationDigest: hash('sha256', ''));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.updates.prepare'));
        $preparation = SystemUpdatePreparation::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.updates.extract', $preparation));

        app(BackupController::class)->create();

        $this->actingAs($admin)
            ->post(route('admin.updates.backup', $preparation))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('success');

        $backup = SystemUpdateBackup::query()->where('preparation_id', $preparation->id)->firstOrFail();
        $this->assertSame('verified', $backup->status);
        $this->assertTrue(Storage::disk('local')->exists($backup->code_snapshot_path));
        $this->assertTrue(Storage::disk('local')->exists($backup->database_backup_path));
        $this->assertTrue(app(UpdateBackupCoordinator::class)->verify($backup));

        $archive = new ZipArchive();
        $this->assertTrue($archive->open(Storage::disk('local')->path($backup->code_snapshot_path)) === true);
        $this->assertNotFalse($archive->locateName('VERSION'));
        $this->assertNotFalse($archive->locateName('artisan'));
        $this->assertFalse($archive->locateName('.env') !== false);
        $this->assertFalse($archive->locateName('storage/app/private') !== false);
        $archive->close();

        $this->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSee('Verified pre-activation backup');

        $this->actingAs($admin)
            ->post(route('admin.updates.preflight', $preparation))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $report = SystemUpdatePreflight::query()->where('preparation_id', $preparation->id)->latest('checked_at')->firstOrFail();
        $backupCheck = collect($report->checks)->firstWhere('key', 'verified_pre_activation_backup');
        $activationCheck = collect($report->checks)->firstWhere('key', 'activation_runner');
        $this->assertSame('pass', $backupCheck['status']);
        $this->assertSame('blocked', $activationCheck['status']);

        Storage::disk('local')->put($backup->database_backup_path, 'tampered-backup');
        $this->assertFalse(app(UpdateBackupCoordinator::class)->verify($backup));
        $this->actingAs($admin)->post(route('admin.updates.preflight', $preparation));
        $tamperedReport = SystemUpdatePreflight::query()->where('preparation_id', $preparation->id)->latest('checked_at')->firstOrFail();
        $tamperedBackupCheck = collect($tamperedReport->checks)->firstWhere('key', 'verified_pre_activation_backup');
        $this->assertSame('blocked', $tamperedBackupCheck['status']);
    }

    public function test_admin_cannot_create_update_backup_without_a_recent_verified_database_media_backup(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $packageBytes = $this->createReleasePackage([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            'storage/.gitignore' => '',
            'bootstrap/cache/.gitignore' => '',
        ]);
        $this->configureSignedRelease(packageBytes: $packageBytes, migrationDigest: hash('sha256', ''));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.updates.prepare'));
        $preparation = SystemUpdatePreparation::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.updates.extract', $preparation));

        $this->actingAs($admin)
            ->post(route('admin.updates.backup', $preparation))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('system_update_backups', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('updates/backups'));
    }

    public function test_non_admin_cannot_create_pre_activation_update_backup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $preparation = SystemUpdatePreparation::create([
            'prepared_by' => $admin->id,
            'version' => '1.0.1',
            'package_sha256' => str_repeat('a', 64),
            'manifest_sha256' => str_repeat('b', 64),
            'migration_set_sha256' => hash('sha256', ''),
            'package_path' => 'updates/staging/test.zip',
            'release_path' => 'updates/releases/test',
            'file_size' => 100,
            'status' => 'staged',
            'message' => 'Test staged package.',
            'expires_at' => now()->addHour(),
            'staged_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('admin.updates.backup', $preparation))
            ->assertForbidden();

        $this->assertDatabaseCount('system_update_backups', 0);
    }

    public function test_admin_can_run_activation_preflight_and_it_blocks_until_verified_backup_exists(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $packageBytes = $this->createReleasePackage([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            'storage/.gitignore' => '',
            'bootstrap/cache/.gitignore' => '',
        ]);
        $this->configureSignedRelease(packageBytes: $packageBytes, migrationDigest: hash('sha256', ''));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.updates.prepare'));
        $preparation = SystemUpdatePreparation::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.updates.extract', $preparation));

        $preparation->refresh();
        $this->assertSame('staged', $preparation->status);

        $this->actingAs($admin)
            ->post(route('admin.updates.preflight', $preparation))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $report = SystemUpdatePreflight::query()->where('preparation_id', $preparation->id)->latest('checked_at')->firstOrFail();
        $this->assertSame('blocked', $report->status);
        $backupCheck = collect($report->checks)->firstWhere('key', 'verified_pre_activation_backup');
        $this->assertSame('blocked', $backupCheck['status']);
        $this->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSee('Latest activation preflight')
            ->assertSee('Verified pre activation backup');
        $this->assertDatabaseHas('system_update_preparations', [
            'id' => $preparation->id,
            'status' => 'staged',
        ]);
    }

    public function test_non_admin_cannot_run_activation_preflight(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $preparation = SystemUpdatePreparation::create([
            'prepared_by' => $admin->id,
            'version' => '1.0.1',
            'package_sha256' => str_repeat('a', 64),
            'manifest_sha256' => str_repeat('b', 64),
            'migration_set_sha256' => hash('sha256', ''),
            'package_path' => 'updates/staging/test.zip',
            'release_path' => 'updates/releases/test',
            'file_size' => 100,
            'status' => 'staged',
            'message' => 'Test staged package.',
            'expires_at' => now()->addHour(),
            'staged_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('admin.updates.preflight', $preparation))
            ->assertForbidden();

        $this->assertDatabaseCount('system_update_preflights', 0);
    }

    public function test_non_admin_cannot_extract_a_verified_release(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $preparation = SystemUpdatePreparation::create([
            'prepared_by' => $admin->id,
            'version' => '1.0.1',
            'package_sha256' => str_repeat('a', 64),
            'manifest_sha256' => str_repeat('b', 64),
            'migration_set_sha256' => hash('sha256', ''),
            'package_path' => 'updates/staging/test.zip',
            'file_size' => 100,
            'status' => 'verified',
            'message' => 'Test package.',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($student)
            ->post(route('admin.updates.extract', $preparation))
            ->assertForbidden();

        $this->assertDatabaseHas('system_update_preparations', [
            'id' => $preparation->id,
            'status' => 'verified',
        ]);
    }

    public function test_extraction_rejects_a_package_modified_after_verification(): void
    {
        $packageBytes = $this->createReleasePackage([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            'storage/.gitignore' => '',
            'bootstrap/cache/.gitignore' => '',
        ]);
        $this->configureSignedRelease(packageBytes: $packageBytes, migrationDigest: hash('sha256', ''));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.updates.prepare'));
        $preparation = SystemUpdatePreparation::query()->firstOrFail();
        Storage::disk('local')->put($preparation->package_path, 'tampered-after-verification');

        $this->actingAs($admin)
            ->post(route('admin.updates.extract', $preparation))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $preparation->refresh();
        $this->assertSame('verified', $preparation->status);
        $this->assertNull($preparation->release_path);
        $this->assertSame([], Storage::disk('local')->allFiles('updates/releases'));
    }

    public function test_expired_verified_package_is_removed_and_cannot_be_extracted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $packagePath = 'updates/staging/expired.zip';
        Storage::disk('local')->put($packagePath, 'expired');
        $preparation = SystemUpdatePreparation::create([
            'prepared_by' => $admin->id,
            'version' => '1.0.1',
            'package_sha256' => str_repeat('a', 64),
            'manifest_sha256' => str_repeat('b', 64),
            'migration_set_sha256' => hash('sha256', ''),
            'package_path' => $packagePath,
            'file_size' => 7,
            'status' => 'verified',
            'message' => 'Expired test package.',
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.updates.extract', $preparation))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $preparation->refresh();
        $this->assertSame('expired', $preparation->status);
        $this->assertFalse(Storage::disk('local')->exists($packagePath));
    }

    public function test_expired_staged_release_removes_both_package_and_extracted_tree(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $packagePath = 'updates/staging/expired-staged.zip';
        $releasePath = 'updates/releases/expired-staged';
        Storage::disk('local')->put($packagePath, 'expired-package');
        Storage::disk('local')->put($releasePath.'/VERSION', "1.0.1\n");
        $preparation = SystemUpdatePreparation::create([
            'prepared_by' => $admin->id,
            'version' => '1.0.1',
            'package_sha256' => str_repeat('a', 64),
            'manifest_sha256' => str_repeat('b', 64),
            'migration_set_sha256' => hash('sha256', ''),
            'package_path' => $packagePath,
            'release_path' => $releasePath,
            'file_size' => 14,
            'status' => 'staged',
            'message' => 'Expired staged release.',
            'expires_at' => now()->subMinute(),
            'staged_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.updates.extract', $preparation))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $preparation->refresh();
        $this->assertSame('expired', $preparation->status);
        $this->assertFalse(Storage::disk('local')->exists($packagePath));
        $this->assertFalse(Storage::disk('local')->exists($releasePath.'/VERSION'));
    }

    public function test_admin_cannot_stage_a_package_redirected_to_an_untrusted_host(): void
    {
        $this->configureSignedRelease(
            packageBytes: 'not-used-because-redirect-is-rejected',
            packageRedirectToUntrustedHost: true
        );
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.prepare'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('system_update_preparations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('updates/staging'));
    }

    public function test_admin_cannot_stage_an_archive_containing_environment_secrets(): void
    {
        $packageBytes = $this->createReleasePackage([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            'deployment/.env.production' => "APP_KEY=secret\n",
            'storage/.gitignore' => '',
            'bootstrap/cache/.gitignore' => '',
        ]);
        $this->configureSignedRelease(packageBytes: $packageBytes, migrationDigest: hash('sha256', ''));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.prepare'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('system_update_preparations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('updates/staging'));
    }

    public function test_admin_cannot_stage_a_package_with_a_mismatched_sha256(): void
    {
        $this->configureSignedRelease(
            packageBytes: 'tampered-package-bytes',
            packageHashOverride: str_repeat('0', 64)
        );
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.prepare'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('system_update_preparations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('updates/staging'));
    }

    public function test_admin_cannot_stage_an_archive_with_unexpected_public_executable_files(): void
    {
        $packageBytes = $this->createReleasePackage([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'public/shell.php' => "<?php echo 'unexpected';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            'storage/.gitignore' => '',
            'bootstrap/cache/.gitignore' => '',
        ]);
        $this->configureSignedRelease(packageBytes: $packageBytes, migrationDigest: hash('sha256', ''));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.prepare'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('system_update_preparations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('updates/staging'));
    }

    public function test_admin_cannot_stage_an_archive_with_path_traversal_entries(): void
    {
        $packageBytes = $this->createReleasePackage([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            '../.env' => 'APP_KEY=secret',
        ]);
        $this->configureSignedRelease(packageBytes: $packageBytes, migrationDigest: hash('sha256', ''));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.prepare'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('system_update_preparations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('updates/staging'));
    }

    public function test_admin_cannot_stage_an_archive_with_symbolic_links(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'aloms-release-symlink-');
        $this->assertIsString($path);
        $archive = new ZipArchive();
        $this->assertTrue($archive->open($path, ZipArchive::OVERWRITE) === true);
        foreach ([
            'artisan' => "<?php echo 'artisan';",
            'public/index.php' => "<?php echo 'public';",
            'vendor/autoload.php' => "<?php echo 'vendor';",
            'VERSION' => "1.0.1\n",
            'storage/.gitignore' => '',
            'bootstrap/cache/.gitignore' => '',
            'app/unsafe-link' => '../../.env',
        ] as $name => $contents) {
            $this->assertTrue($archive->addFromString($name, $contents));
        }
        $this->assertTrue($archive->setExternalAttributesName(
            'app/unsafe-link',
            ZipArchive::OPSYS_UNIX,
            (0120000 | 0777) << 16
        ));
        $this->assertTrue($archive->close());
        $packageBytes = file_get_contents($path);
        @unlink($path);
        $this->assertIsString($packageBytes);

        $this->configureSignedRelease(packageBytes: $packageBytes, migrationDigest: hash('sha256', ''));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.prepare'))
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('system_update_preparations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('updates/staging'));
    }

    private function configureSignedRelease(?string $signatureOverride = null, string $version = '1.0.1', bool $redirectToUntrustedHost = false, ?string $packageBytes = null, ?string $migrationDigest = null, bool $packageRedirectToUntrustedHost = false, ?string $packageHashOverride = null): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 3072,
        ]);
        $this->assertNotFalse($privateKey);

        $details = openssl_pkey_get_details($privateKey);
        $this->assertIsArray($details);
        $publicPem = $details['key'];
        $this->publicKeyPath = tempnam(sys_get_temp_dir(), 'aloms-release-key-');
        $this->assertNotFalse($this->publicKeyPath);
        file_put_contents($this->publicKeyPath, $publicPem);

        $derBase64 = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $publicPem);
        $der = base64_decode((string) $derBase64, true);
        $fingerprint = hash('sha256', $der);

        $manifest = [
            'product' => 'aloms-english-center-lms',
            'version' => $version,
            'release_channel' => 'stable',
            'git_commit' => str_repeat('a', 40),
            'minimum_php' => '8.3.0',
            'laravel_major' => 12,
            'required_extensions' => ['openssl', 'pdo_mysql'],
            'package_file' => 'AlomsEnglishCenter-cpanel.zip',
            'package_sha256' => $packageHashOverride ?? hash('sha256', $packageBytes ?? ''),
            'migration_set_sha256' => $migrationDigest ?? str_repeat('c', 64),
            'signing_public_key_sha256' => $fingerprint,
            'published_at' => '2026-11-10T00:00:00Z',
            'artifact_url' => 'https://github.com/lalchandakand-LMS/finalLMS/releases/download/v'.$version.'/AlomsEnglishCenter-cpanel.zip',
        ];
        $manifestBytes = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        openssl_sign($manifestBytes, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $manifestUrl = 'https://github.com/lalchandakand-LMS/finalLMS/releases/latest/download/release-manifest.json';
        $signatureUrl = 'https://github.com/lalchandakand-LMS/finalLMS/releases/latest/download/release-manifest.sig';
        $manifestResponse = $redirectToUntrustedHost
            ? Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])
            : Http::response($manifestBytes, 200);
        $fakeResponses = [
            $manifestUrl => $manifestResponse,
            $signatureUrl => Http::response($signatureOverride ?? $signature, 200),
        ];
        if ($packageBytes !== null) {
            $fakeResponses[$manifest['artifact_url']] = $packageRedirectToUntrustedHost
                ? Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])
                : Http::response($packageBytes, 200);
        }
        Http::fake($fakeResponses);

        config([
            'services.lms_updates.enabled' => true,
            'services.lms_updates.manifest_url' => $manifestUrl,
            'services.lms_updates.public_key_path' => $this->publicKeyPath,
            'services.lms_updates.channel' => 'stable',
            'services.lms_updates.current_version' => '1.0.0',
        ]);
    }
    private function createReleasePackage(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aloms-release-package-');
        $this->assertIsString($path);
        $archive = new ZipArchive();
        $this->assertTrue($archive->open($path, ZipArchive::OVERWRITE) === true);

        foreach ($files as $name => $contents) {
            $this->assertTrue($archive->addFromString($name, $contents));
        }

        $this->assertTrue($archive->close());
        $bytes = file_get_contents($path);
        @unlink($path);
        $this->assertIsString($bytes);

        return $bytes;
    }

}
