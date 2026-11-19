<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class ThemeManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_theme_manager_actions(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.themes.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.themes.reset'))->assertForbidden();
        $this->actingAs($student)->delete(route('admin.themes.destroy', 1))->assertForbidden();
    }

    public function test_admin_can_open_theme_manager_and_sees_signing_key_requirement(): void
    {
        config(['themes.public_key' => null]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.themes.index'))
            ->assertOk()
            ->assertSee('Theme Manager')
            ->assertSee('THEME_PUBLIC_KEY');
    }

    public function test_theme_zip_install_is_blocked_until_trusted_public_key_is_configured(): void
    {
        config(['themes.public_key' => null]);
        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->createWithContent('theme.zip', 'not a real archive');

        $this->actingAs($admin)->post(route('admin.themes.install'), ['package' => $file])
            ->assertRedirect()
            ->assertSessionHasErrors('package');

        $this->assertDatabaseCount('themes', 0);
    }

    public function test_reset_restores_builtin_theme_without_changing_website_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('active_theme_id', 22);
        Setting::setValue('active_theme_slug', 'old-theme');
        Setting::setValue('active_theme_version', '1.0.0');
        Setting::setValue('website_sections', '[{"type":"courses","enabled":true}]');

        $this->actingAs($admin)->post(route('admin.themes.reset'))
            ->assertRedirect(route('admin.themes.index'))
            ->assertSessionHas('success');

        $this->assertSame('0', (string) Setting::value('active_theme_id'));
        $this->assertSame('', (string) Setting::value('active_theme_slug'));
        $this->assertSame('[{"type":"courses","enabled":true}]', Setting::value('website_sections'));
        $this->assertDatabaseHas('theme_activations', ['action' => 'reset', 'actor_id' => $admin->id, 'theme_id' => null]);
        $history = DB::table('theme_activations')->where('action', 'reset')->latest('id')->first();
        $this->assertSame('old-theme', json_decode($history->previous_state, true)['slug']);
    }

    public function test_theme_manager_displays_activation_history_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DB::table('theme_activations')->insert([
            'theme_id' => null,
            'action' => 'reset',
            'previous_state' => json_encode(['id' => 0, 'slug' => 'legacy-blue', 'version' => '1.0.0']),
            'actor_id' => $admin->id,
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.themes.index'))
            ->assertOk()
            ->assertSee('Activation History')
            ->assertSee('legacy-blue')
            ->assertSee($admin->name);
    }

    public function test_admin_can_preview_and_activate_an_installed_theme(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $path = 'themes/blue-academy/1.0.0/assets/theme.css';
        Storage::disk('public')->put($path, '.theme-preview-shell { --aec-theme-preview-marker: 24px; }');
        $themeId = DB::table('themes')->insertGetId([
            'slug' => 'blue-academy',
            'version' => '1.0.0',
            'name' => 'Blue Academy',
            'author' => 'Alom',
            'description' => 'A test theme',
            'min_lms_version' => '1.0.0',
            'manifest' => json_encode([
                'product_id' => 'aloms-lms-theme',
                'assets' => ['assets/theme.css' => hash('sha256', '.theme-preview-shell { --aec-theme-preview-marker: 24px; }')],
            ]),
            'assets' => json_encode(['assets/theme.css']),
            'preview_path' => null,
            'package_path' => 'themes/packages/test.zip',
            'package_sha256' => str_repeat('a', 64),
            'installed_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.themes.preview', $themeId))
            ->assertOk()
            ->assertSee('Blue Academy')
            ->assertSee('sandbox', false)
            ->assertSee(route('admin.themes.preview.frame', $themeId))
            ->assertDontSee('--aec-theme-preview-marker: 24px', false);

        $this->actingAs($admin)->get(route('admin.themes.preview.frame', $themeId))
            ->assertOk()
            ->assertHeader('Content-Security-Policy')
            ->assertSee('.theme-preview-shell', false)
            ->assertSee('--aec-theme-preview-marker: 24px', false);

        $this->actingAs($admin)->post(route('admin.themes.activate', $themeId))
            ->assertRedirect(route('admin.themes.index'));

        $this->assertSame((string) $themeId, (string) Setting::value('active_theme_id'));
        $this->assertSame('blue-academy', Setting::value('active_theme_slug'));
        $this->assertDatabaseHas('theme_activations', ['theme_id' => $themeId, 'action' => 'activate']);
    }

    public function test_theme_activation_rejects_assets_changed_after_installation(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $expected = 'body { color: #123456; }';
        $path = 'themes/blue-academy/1.0.0/assets/theme.css';
        Storage::disk('public')->put($path, 'body { color: #ff0000; }');
        $themeId = DB::table('themes')->insertGetId([
            'slug' => 'blue-academy',
            'version' => '1.0.0',
            'name' => 'Blue Academy',
            'min_lms_version' => '1.0.0',
            'manifest' => json_encode([
                'product_id' => 'aloms-lms-theme',
                'assets' => ['assets/theme.css' => hash('sha256', $expected)],
            ]),
            'assets' => json_encode(['assets/theme.css']),
            'package_path' => 'themes/packages/test.zip',
            'package_sha256' => str_repeat('c', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Setting::setValue('active_theme_id', 0);
        $this->actingAs($admin)->post(route('admin.themes.activate', $themeId))
            ->assertRedirect()
            ->assertSessionHasErrors('theme');

        $this->assertSame('0', (string) Setting::value('active_theme_id'));
        $this->assertDatabaseMissing('theme_activations', ['theme_id' => $themeId, 'action' => 'activate']);
    }

    public function test_theme_activation_rejects_invalid_compatibility_metadata(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $contents = 'body { color: #123456; }';
        Storage::disk('public')->put('themes/blue-academy/1.0.0/assets/theme.css', $contents);
        $themeId = DB::table('themes')->insertGetId([
            'slug' => 'blue-academy',
            'version' => '1.0.0',
            'name' => 'Blue Academy',
            'min_lms_version' => '1.0.0',
            'manifest' => json_encode([
                'product_id' => 'aloms-lms-theme',
                'max_lms_version' => 'not-a-version',
                'assets' => ['assets/theme.css' => hash('sha256', $contents)],
            ]),
            'assets' => json_encode(['assets/theme.css']),
            'package_path' => 'themes/packages/test.zip',
            'package_sha256' => str_repeat('e', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Setting::setValue('active_theme_id', 0);
        $this->actingAs($admin)->post(route('admin.themes.activate', $themeId))
            ->assertRedirect()
            ->assertSessionHasErrors('theme');

        $this->assertSame('0', (string) Setting::value('active_theme_id'));
        $this->assertDatabaseMissing('theme_activations', ['theme_id' => $themeId, 'action' => 'activate']);
    }

    public function test_public_theme_asset_route_only_serves_registered_css_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('themes/blue-academy/1.0.0/assets/theme.css', 'body { color: #123456; }');
        DB::table('themes')->insert([
            'slug' => 'blue-academy',
            'version' => '1.0.0',
            'name' => 'Blue Academy',
            'min_lms_version' => '1.0.0',
            'manifest' => json_encode([
                'product_id' => 'aloms-lms-theme',
                'assets' => ['assets/theme.css' => hash('sha256', 'body { color: #123456; }')],
            ]),
            'assets' => json_encode(['assets/theme.css']),
            'package_path' => 'themes/packages/test.zip',
            'package_sha256' => str_repeat('b', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('themes.asset', ['slug' => 'blue-academy', 'version' => '1.0.0', 'file' => 'theme.css']))
            ->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8')->assertSee('color: #123456', false);
        $this->get(route('themes.asset', ['slug' => 'blue-academy', 'version' => '1.0.0', 'file' => 'other.css']))
            ->assertNotFound();

        Storage::disk('public')->put('themes/blue-academy/1.0.0/assets/theme.css', 'body { color: #ff0000; }');
        $this->get(route('themes.asset', ['slug' => 'blue-academy', 'version' => '1.0.0', 'file' => 'theme.css']))
            ->assertNotFound();
    }

    public function test_signed_theme_zip_installs_without_activating(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP ZIP extension is unavailable.');
        }

        Storage::fake('public');
        Storage::fake('local');
        $keyPair = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $details = openssl_pkey_get_details($keyPair);
        config(['themes.public_key' => $details['key']]);

        $manifest = json_encode([
            'product_id' => 'aloms-lms-theme',
            'slug' => 'signed-blue',
            'version' => '1.0.0',
            'name' => 'Signed Blue',
            'author' => 'Alom',
            'min_lms_version' => '1.0.0',
            'assets' => ['assets/theme.css' => hash('sha256', 'body { color: #123456; }')],
        ], JSON_UNESCAPED_SLASHES);
        openssl_sign($manifest, $signature, $keyPair, OPENSSL_ALGO_SHA256);

        $zipPath = tempnam(sys_get_temp_dir(), 'aec-theme-');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('theme.json', $manifest);
        $zip->addFromString('theme.sig', base64_encode($signature));
        $zip->addFromString('assets/', '');
        $zip->addFromString('assets/theme.css', 'body { color: #123456; }');
        $zip->close();

        $admin = User::factory()->create(['role' => 'admin']);
        $upload = new UploadedFile($zipPath, 'signed-theme.zip', 'application/zip', null, true);
        $this->actingAs($admin)->post(route('admin.themes.install'), ['package' => $upload])
            ->assertRedirect(route('admin.themes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('themes', ['slug' => 'signed-blue', 'version' => '1.0.0']);
        $this->assertSame('0', (string) Setting::value('active_theme_id', 0));
        Storage::disk('public')->assertExists('themes/signed-blue/1.0.0/assets/theme.css');

        // A duplicate package must be rejected before it can overwrite the
        // existing version's shared asset path or remove the installed theme.
        $duplicateUpload = new UploadedFile($zipPath, 'signed-theme-duplicate.zip', 'application/zip', null, true);
        $this->actingAs($admin)->post(route('admin.themes.install'), ['package' => $duplicateUpload])
            ->assertRedirect()
            ->assertSessionHasErrors('package');
        $this->assertDatabaseCount('themes', 1);
        Storage::disk('public')->assertExists('themes/signed-blue/1.0.0/assets/theme.css');
        Storage::disk('local')->assertExists(DB::table('themes')->value('package_path'));
        @unlink($zipPath);
    }

    public function test_theme_installation_refuses_to_overwrite_orphaned_storage_paths(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP ZIP extension is unavailable.');
        }

        Storage::fake('public');
        Storage::fake('local');
        $orphanPath = 'themes/orphaned-theme/1.0.0/assets/theme.css';
        Storage::disk('public')->put($orphanPath, 'existing orphan must be preserved');
        $keyPair = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $details = openssl_pkey_get_details($keyPair);
        config(['themes.public_key' => $details['key']]);

        $manifest = json_encode([
            'product_id' => 'aloms-lms-theme',
            'slug' => 'orphaned-theme',
            'version' => '1.0.0',
            'name' => 'Orphaned Theme',
            'min_lms_version' => '1.0.0',
            'assets' => ['assets/theme.css' => hash('sha256', 'new theme css')],
        ], JSON_UNESCAPED_SLASHES);
        openssl_sign($manifest, $signature, $keyPair, OPENSSL_ALGO_SHA256);
        $zipPath = tempnam(sys_get_temp_dir(), 'aec-theme-orphan-');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('theme.json', $manifest);
        $zip->addFromString('theme.sig', base64_encode($signature));
        $zip->addFromString('assets/', '');
        $zip->addFromString('assets/theme.css', 'new theme css');
        $zip->close();

        $admin = User::factory()->create(['role' => 'admin']);
        $upload = new UploadedFile($zipPath, 'orphaned-theme.zip', 'application/zip', null, true);
        $this->actingAs($admin)->post(route('admin.themes.install'), ['package' => $upload])
            ->assertRedirect()
            ->assertSessionHasErrors('package');

        $this->assertDatabaseCount('themes', 0);
        $this->assertSame('existing orphan must be preserved', Storage::disk('public')->get($orphanPath));
        $this->assertSame([], Storage::disk('local')->allFiles('themes/packages'));
        @unlink($zipPath);
    }

    public function test_theme_installation_fails_closed_when_lifecycle_lock_is_missing(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP ZIP extension is unavailable.');
        }

        Storage::fake('public');
        Storage::fake('local');
        $keyPair = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $details = openssl_pkey_get_details($keyPair);
        config(['themes.public_key' => $details['key']]);

        $manifest = json_encode([
            'product_id' => 'aloms-lms-theme',
            'slug' => 'lock-required',
            'version' => '1.0.0',
            'name' => 'Lock Required',
            'min_lms_version' => '1.0.0',
            'assets' => ['assets/theme.css' => hash('sha256', 'body { color: #123456; }')],
        ], JSON_UNESCAPED_SLASHES);
        openssl_sign($manifest, $signature, $keyPair, OPENSSL_ALGO_SHA256);
        $zipPath = tempnam(sys_get_temp_dir(), 'aec-theme-lock-');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('theme.json', $manifest);
        $zip->addFromString('theme.sig', base64_encode($signature));
        $zip->addFromString('assets/', '');
        $zip->addFromString('assets/theme.css', 'body { color: #123456; }');
        $zip->close();

        DB::table('theme_manager_locks')->where('id', 1)->delete();
        $admin = User::factory()->create(['role' => 'admin']);
        $upload = new UploadedFile($zipPath, 'lock-required.zip', 'application/zip', null, true);
        $this->actingAs($admin)->post(route('admin.themes.install'), ['package' => $upload])
            ->assertRedirect()
            ->assertSessionHasErrors('package');

        $this->assertDatabaseCount('themes', 0);
        Storage::disk('public')->assertMissing('themes/lock-required/1.0.0/assets/theme.css');
        $this->assertSame([], Storage::disk('local')->allFiles('themes/packages'));
        @unlink($zipPath);
    }

    public function test_active_theme_cannot_be_deleted(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $packagePath = 'themes/packages/123e4567-e89b-12d3-a456-426614174000.zip';
        $cssPath = 'themes/keep-theme/1.0.0/assets/theme.css';
        Storage::disk('public')->put($cssPath, 'body { color: #123456; }');
        Storage::disk('local')->put($packagePath, 'signed package');
        $themeId = DB::table('themes')->insertGetId([
            'slug' => 'keep-theme',
            'version' => '1.0.0',
            'name' => 'Keep Theme',
            'min_lms_version' => '1.0.0',
            'manifest' => json_encode(['product_id' => 'aloms-lms-theme', 'assets' => ['assets/theme.css' => hash('sha256', 'body { color: #123456; }')]]),
            'assets' => json_encode(['assets/theme.css']),
            'package_path' => $packagePath,
            'package_sha256' => str_repeat('a', 64),
            'installed_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Setting::setValue('active_theme_id', $themeId);
        Setting::setValue('active_theme_slug', 'keep-theme');
        Setting::setValue('active_theme_version', '1.0.0');

        $this->actingAs($admin)->delete(route('admin.themes.destroy', $themeId))
            ->assertRedirect()
            ->assertSessionHasErrors('theme');

        $this->assertDatabaseHas('themes', ['id' => $themeId]);
        Storage::disk('public')->assertExists($cssPath);
        Storage::disk('local')->assertExists($packagePath);
        $this->assertDatabaseMissing('theme_activations', ['action' => 'delete']);
    }

    public function test_inactive_theme_deletion_removes_only_package_files_and_keeps_audit_and_site_content(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $packagePath = 'themes/packages/123e4567-e89b-12d3-a456-426614174000.zip';
        $cssPath = 'themes/remove-theme/1.0.0/assets/theme.css';
        $previewPath = 'themes/remove-theme/1.0.0/preview.png';
        Storage::disk('public')->put($cssPath, 'body { color: #123456; }');
        Storage::disk('public')->put($previewPath, 'preview bytes');
        Storage::disk('local')->put($packagePath, 'signed package');
        $themeId = DB::table('themes')->insertGetId([
            'slug' => 'remove-theme',
            'version' => '1.0.0',
            'name' => 'Remove Theme',
            'min_lms_version' => '1.0.0',
            'manifest' => json_encode(['product_id' => 'aloms-lms-theme', 'assets' => ['assets/theme.css' => hash('sha256', 'body { color: #123456; }')]]),
            'assets' => json_encode(['assets/theme.css']),
            'preview_path' => $previewPath,
            'package_path' => $packagePath,
            'package_sha256' => str_repeat('b', 64),
            'installed_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Setting::setValue('active_theme_id', 0);
        Setting::setValue('active_theme_slug', '');
        Setting::setValue('active_theme_version', '');
        Setting::setValue('website_sections', '[{"type":"courses","enabled":true}]');

        $this->actingAs($admin)->delete(route('admin.themes.destroy', $themeId))
            ->assertRedirect(route('admin.themes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('themes', ['id' => $themeId]);
        $this->actingAs($admin)->get(route('admin.themes.index'))
            ->assertOk()
            ->assertSee('Remove Theme')
            ->assertSee('Delete');
        $this->assertDatabaseHas('theme_activations', [
            'theme_id' => null,
            'action' => 'delete',
            'actor_id' => $admin->id,
        ]);
        $history = DB::table('theme_activations')->where('action', 'delete')->latest('id')->first();
        $this->assertSame('remove-theme', json_decode($history->previous_state, true)['slug']);
        $this->assertSame('[{"type":"courses","enabled":true}]', Setting::value('website_sections'));
        Storage::disk('public')->assertMissing($cssPath);
        Storage::disk('public')->assertMissing($previewPath);
        Storage::disk('local')->assertMissing($packagePath);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'theme.deleted',
            'method' => 'DELETE',
        ]);
    }

    public function test_theme_deletion_refuses_untrusted_asset_paths_without_removing_the_record(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $themeId = DB::table('themes')->insertGetId([
            'slug' => 'unsafe-theme',
            'version' => '1.0.0',
            'name' => 'Unsafe Theme',
            'min_lms_version' => '1.0.0',
            'manifest' => json_encode(['product_id' => 'aloms-lms-theme', 'assets' => []]),
            'assets' => json_encode(['assets/../../outside.css']),
            'package_path' => 'themes/packages/123e4567-e89b-12d3-a456-426614174000.zip',
            'package_sha256' => str_repeat('c', 64),
            'installed_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('admin.themes.destroy', $themeId))
            ->assertRedirect()
            ->assertSessionHasErrors('theme');

        $this->assertDatabaseHas('themes', ['id' => $themeId]);
        $this->assertDatabaseMissing('theme_activations', ['action' => 'delete']);
    }


    public function test_theme_archive_entry_sizes_are_checked_before_contents_are_read(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP ZIP extension is unavailable.');
        }

        $keyPair = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $details = openssl_pkey_get_details($keyPair);
        config(['themes.public_key' => $details['key']]);

        $zipPath = tempnam(sys_get_temp_dir(), 'aec-oversized-theme-');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('theme.json', '{}');
        $zip->addFromString('theme.sig', 'not-a-signature');
        $zip->addFromString('assets/', '');
        $zip->addFromString('assets/oversized.css', str_repeat('a', 262145));
        $zip->close();

        $admin = User::factory()->create(['role' => 'admin']);
        $upload = new UploadedFile($zipPath, 'oversized-theme.zip', 'application/zip', null, true);
        $this->actingAs($admin)->post(route('admin.themes.install'), ['package' => $upload])
            ->assertRedirect()
            ->assertSessionHasErrors('package');

        $this->assertDatabaseCount('themes', 0);
        @unlink($zipPath);
    }

    public function test_shared_layout_renders_before_theme_manager_tables_exist(): void
    {
        Schema::partialMock()
            ->shouldReceive('hasTable')
            ->andReturnUsing(fn (string $table): bool => $table !== 'themes'
                && DB::connection()->getSchemaBuilder()->hasTable($table));

        $this->get(route('landing'))->assertOk();
    }

    public function test_theme_deletion_refuses_corrupted_preview_and_package_paths(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([
            ['slug' => 'bad-preview', 'preview_path' => 'uploads/keep.png', 'package_path' => 'themes/packages/123e4567-e89b-12d3-a456-426614174000.zip'],
            ['slug' => 'bad-package', 'preview_path' => null, 'package_path' => 'private/keep.zip'],
        ] as $case) {
            $themeId = DB::table('themes')->insertGetId([
                'slug' => $case['slug'],
                'version' => '1.0.0',
                'name' => 'Corrupted Metadata',
                'min_lms_version' => '1.0.0',
                'manifest' => json_encode(['product_id' => 'aloms-lms-theme', 'assets' => []]),
                'assets' => json_encode([]),
                'preview_path' => $case['preview_path'],
                'package_path' => $case['package_path'],
                'package_sha256' => str_repeat('d', 64),
                'installed_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->actingAs($admin)->delete(route('admin.themes.destroy', $themeId))
                ->assertRedirect()
                ->assertSessionHasErrors('theme');

            $this->assertDatabaseHas('themes', ['id' => $themeId]);
        }

        $this->assertDatabaseMissing('theme_activations', ['action' => 'delete']);
    }

}
