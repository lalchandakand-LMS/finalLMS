<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_dashboard_theme_and_section_visibility(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'dashboard_primary_color' => '#102b63',
            'dashboard_secondary_color' => '#0759c8',
            'dashboard_accent_color' => '#ffd52f',
            'dashboard_background_color' => '#f3f6ff',
            'dashboard_surface_color' => '#ffffff',
            'dashboard_students_color' => '#1675f5',
            'dashboard_teachers_color' => '#08b978',
            'dashboard_courses_color' => '#8525e8',
            'dashboard_certificates_color' => '#f59e0b',
            'dashboard_show_hero' => '1',
            'dashboard_show_statistics' => '0',
            'dashboard_show_live_class' => '1',
            'dashboard_show_quick_actions' => '0',
            'dashboard_show_system_snapshot' => '1',
            'dashboard_show_learning_operations' => '0',
            'dashboard_order_hero' => '2',
            'dashboard_order_statistics' => '1',
            'dashboard_order_live_class' => '3',
            'dashboard_order_quick_actions' => '5',
            'dashboard_order_system_snapshot' => '4',
            'dashboard_order_learning_operations' => '6',
        ])->assertRedirect(route('admin.settings.index'));

        $this->assertDatabaseHas('settings', ['key' => 'dashboard_primary_color', 'value' => '#102b63']);
        $this->assertDatabaseHas('settings', ['key' => 'dashboard_students_color', 'value' => '#1675f5']);
        $this->assertDatabaseHas('settings', ['key' => 'dashboard_show_statistics', 'value' => '0']);
        $this->assertDatabaseHas('settings', ['key' => 'dashboard_show_quick_actions', 'value' => '0']);
        $this->assertDatabaseHas('settings', ['key' => 'dashboard_order_hero', 'value' => '2']);
        $this->assertDatabaseHas('settings', ['key' => 'dashboard_order_statistics', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['key' => 'dashboard_order_quick_actions', 'value' => '5']);
        $this->assertDatabaseHas('settings', ['key' => 'dashboard_show_learning_operations', 'value' => '0']);
    }

    public function test_invalid_dashboard_colors_and_non_image_media_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $document = MediaAsset::query()->create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'documents/guide.pdf',
            'original_name' => 'guide.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), [
                'dashboard_primary_color' => 'red; background:url(javascript:alert(1))',
                'dashboard_hero_image_media_id' => $document->id,
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors(['dashboard_primary_color', 'dashboard_hero_image_media_id']);
    }

    public function test_admin_dashboard_uses_saved_theme_and_visibility_preferences(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('dashboard_primary_color', '#123456');
        Setting::setValue('dashboard_show_statistics', '0');
        Setting::setValue('dashboard_show_quick_actions', '0');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--aec-primary:#123456', false)
            ->assertDontSee('Academy overview')
            ->assertDontSee('Quick actions');
    }

    public function test_invalid_dashboard_widget_positions_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), [
                'dashboard_order_hero' => 0,
                'dashboard_order_statistics' => 7,
                'dashboard_order_live_class' => 'first',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors([
                'dashboard_order_hero',
                'dashboard_order_statistics',
                'dashboard_order_live_class',
            ]);
    }

    public function test_non_admin_cannot_change_dashboard_appearance_settings(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->post(route('admin.settings.update'), ['dashboard_primary_color' => '#123456'])
            ->assertForbidden();
    }
}
