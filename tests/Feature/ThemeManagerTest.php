<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $this->assertDatabaseHas('theme_activations', ['action' => 'reset', 'actor_id' => $admin->id]);
    }

    public function test_admin_can_preview_and_activate_an_installed_theme(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $path = 'themes/blue-academy/1.0.0/assets/theme.css';
        Storage::disk('public')->put($path, '.theme-preview-shell { border-radius: 24px; }');
        $themeId = DB::table('themes')->insertGetId([
            'slug' => 'blue-academy',
            'version' => '1.0.0',
            'name' => 'Blue Academy',
            'author' => 'Alom',
            'description' => 'A test theme',
            'min_lms_version' => '1.0.0',
            'manifest' => json_encode([
                'product_id' => 'aloms-lms-theme',
                'assets' => ['assets/theme.css' => hash('sha256', '.theme-preview-shell { border-radius: 24px; }')],
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
            ->assertOk()->assertSee('Blue Academy')->assertSee('.theme-preview-shell', false);

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

    public function test_public_theme_asset_route_only_serves_registered_css_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('themes/blue-academy/1.0.0/assets/theme.css', 'body { color: #123456; }');
        DB::table('themes')->insert([
            'slug' => 'blue-academy',
            'version' => '1.0.0',
            'name' => 'Blue Academy',
            'min_lms_version' => '1.0.0',
            'manifest' => '{}',
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
        @unlink($zipPath);
    }
}
