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

    public function test_admin_can_reorder_dashboard_widgets_with_drag_and_accessible_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('data-dashboard-widget-order-list', false)
            ->assertSee('data-dashboard-widget-order-item', false)
            ->assertSee('data-widget-move-up', false)
            ->assertSee('data-widget-move-down', false)
            ->assertSee('data-dashboard-order-status', false)
            ->assertSee('Drag widgets to reorder');
    }

    public function test_dashboard_settings_offer_responsive_preview_and_restore_defaults(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('data-dashboard-preview', false)
            ->assertSee('name="dashboard_theme_mode"', false)
            ->assertSee('data-dashboard-palette-preset', false)
            ->assertSee('value="blue"', false)
            ->assertSee('value="emerald"', false)
            ->assertSee('name="dashboard_card_radius"', false)
            ->assertSee('name="dashboard_card_shadow"', false)
            ->assertSee('name="dashboard_hero_eyebrow"', false)
            ->assertSee('name="dashboard_hero_cta_label"', false)
            ->assertSee('name="dashboard_hero_image_position"', false)
            ->assertSee('data-dashboard-preview-eyebrow', false)
            ->assertSee('data-dashboard-preview-button', false)
            ->assertSee('data-dashboard-preview-mode="desktop"', false)
            ->assertSee('data-dashboard-preview-mode="tablet"', false)
            ->assertSee('data-dashboard-preview-mode="mobile"', false)
            ->assertSee('data-dashboard-restore', false)
            ->assertSee('Restore dashboard defaults');
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

    public function test_admin_can_customize_dashboard_theme_cards_and_hero_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'dashboard_theme_mode' => 'dark',
            'dashboard_card_radius' => 26,
            'dashboard_card_shadow' => 'glow',
            'dashboard_hero_eyebrow' => 'Alom’s English Center LMS',
            'dashboard_hero_cta_label' => 'Open Admin Workspace',
            'dashboard_hero_image_position' => 'right',
        ])->assertRedirect(route('admin.settings.index'));

        $this->assertDatabaseHas('settings', ['key' => 'dashboard_theme_mode', 'value' => 'dark']);
        $this->assertDatabaseHas('settings', ['key' => 'dashboard_card_radius', 'value' => '26']);
        $this->assertDatabaseHas('settings', ['key' => 'dashboard_card_shadow', 'value' => 'glow']);
        $this->assertDatabaseHas('settings', ['key' => 'dashboard_hero_cta_label', 'value' => 'Open Admin Workspace']);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false)
            ->assertSee('data-shadow="glow"', false)
            ->assertSee('--aec-radius:26px', false)
            ->assertSee('Open Admin Workspace');
    }

    public function test_dashboard_customizer_rejects_unsafe_style_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), [
                'dashboard_theme_mode' => 'external',
                'dashboard_card_radius' => 90,
                'dashboard_card_shadow' => 'url-javascript',
                'dashboard_hero_image_position' => 'expression(alert(1))',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors([
                'dashboard_theme_mode',
                'dashboard_card_radius',
                'dashboard_card_shadow',
                'dashboard_hero_image_position',
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
