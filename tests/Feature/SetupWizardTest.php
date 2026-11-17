<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_profile_and_resume_on_academic_year_step(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.setup-wizard.update'), [
            'step' => 1,
            'institution_name' => 'Alom Learning Center',
            'institution_default_locale' => 'bn',
            'institution_timezone' => 'Asia/Dhaka',
            'site_primary_color' => '#123456',
        ])->assertRedirect(route('admin.setup-wizard.index', ['step' => 2]));

        $this->assertDatabaseHas('settings', ['key' => 'institution_name', 'value' => 'Alom Learning Center']);
        $this->assertDatabaseHas('settings', ['key' => 'setup_wizard_step', 'value' => '2']);

        $this->actingAs($admin)->get(route('admin.setup-wizard.index'))
            ->assertOk()->assertSee('শিক্ষাবর্ষ');
    }

    public function test_admin_can_select_logo_and_favicon_from_media_library_during_setup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $logo = MediaAsset::query()->create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'media/academy-logo.png',
            'original_name' => 'academy-logo.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ]);
        $favicon = MediaAsset::query()->create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'media/academy-icon.png',
            'original_name' => 'academy-icon.png',
            'mime_type' => 'image/png',
            'size' => 512,
        ]);

        $this->actingAs($admin)->post(route('admin.setup-wizard.update'), [
            'step' => 1,
            'institution_name' => 'Alom Learning Center',
            'institution_logo_url' => 'https://example.test/logo.png',
            'institution_favicon_url' => 'https://example.test/favicon.png',
            'site_logo_media_id' => $logo->id,
            'site_favicon_media_id' => $favicon->id,
            'institution_default_locale' => 'bn',
            'institution_timezone' => 'Asia/Dhaka',
            'site_primary_color' => '#123456',
        ])->assertRedirect(route('admin.setup-wizard.index', ['step' => 2]));

        $this->assertSame((string) $logo->id, Setting::value('site_logo_media_id'));
        $this->assertSame((string) $favicon->id, Setting::value('site_favicon_media_id'));
        $this->assertSame('https://example.test/logo.png', Setting::value('institution_logo_url'));
    }

    public function test_setup_wizard_rejects_non_image_media_for_logo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $document = MediaAsset::query()->create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'media/notes.pdf',
            'original_name' => 'notes.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $this->actingAs($admin)->from(route('admin.setup-wizard.index', ['step' => 1]))
            ->post(route('admin.setup-wizard.update'), [
                'step' => 1,
                'institution_name' => 'Alom Learning Center',
                'site_logo_media_id' => $document->id,
                'institution_default_locale' => 'bn',
                'institution_timezone' => 'Asia/Dhaka',
                'site_primary_color' => '#123456',
            ])->assertRedirect(route('admin.setup-wizard.index', ['step' => 1]))
            ->assertSessionHasErrors('site_logo_media_id');

        $this->assertSame(null, Setting::value('site_logo_media_id'));
        $this->assertSame('1', Setting::value('setup_wizard_step', '1'));
    }

    public function test_admin_can_save_academic_year_and_complete_payment_step(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.setup-wizard.update'), [
            'step' => 2,
            'academic_year_name' => '2026–2027',
            'academic_year_start' => '2026-07-01',
            'academic_year_end' => '2027-06-30',
        ])->assertRedirect(route('admin.setup-wizard.index', ['step' => 3]));

        $this->actingAs($admin)->post(route('admin.setup-wizard.update'), [
            'step' => 3,
            'payment_methods' => [
                'bkash' => ['enabled' => '1', 'account' => '01700000000', 'instructions' => 'Send money'],
                'rocket' => ['enabled' => '0', 'account' => '', 'instructions' => ''],
                'bank' => ['enabled' => '0', 'account' => '', 'instructions' => ''],
                'manual' => ['enabled' => '1', 'account' => 'Office', 'instructions' => 'Pay at office'],
            ],
        ])->assertRedirect(route('admin.setup-wizard.index'));

        $this->assertNotEmpty(Setting::value('setup_wizard_completed_at'));
        $this->assertSame('01700000000', Setting::paymentMethods()['bkash']['account']);
        $this->assertTrue((bool) Setting::paymentMethods()['bkash']['enabled']);
    }

    public function test_invalid_academic_year_dates_are_rejected_and_progress_is_not_advanced(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.setup-wizard.index', ['step' => 2]))
            ->post(route('admin.setup-wizard.update'), [
                'step' => 2,
                'academic_year_name' => '2026',
                'academic_year_start' => '2027-01-01',
                'academic_year_end' => '2026-01-01',
            ])->assertRedirect(route('admin.setup-wizard.index', ['step' => 2]))
            ->assertSessionHasErrors('academic_year_end');

        $this->assertSame('1', Setting::value('setup_wizard_step', '1'));
    }

    public function test_non_admin_cannot_access_setup_wizard(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.setup-wizard.index'))->assertForbidden();
    }
}
