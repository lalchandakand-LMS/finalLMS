<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_orange_theme_colors_and_the_layout_uses_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.settings.theme.update'), [
            'theme_primary' => '#C2410C',
            'theme_secondary' => '#9A3412',
            'theme_accent' => '#FED7AA',
            'theme_sidebar_start' => '#7C2D12',
            'theme_sidebar_end' => '#431407',
        ])->assertRedirect(route('admin.settings.index'));

        $this->assertDatabaseHas('settings', ['key' => 'theme_primary', 'value' => '#C2410C']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--primary: #C2410C', false)
            ->assertSee('--sidebar-start: #7C2D12', false);
    }

    public function test_theme_rejects_colors_that_do_not_meet_white_text_contrast(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.theme.update'), [
                'theme_primary' => '#FFFFFF',
                'theme_secondary' => '#9A3412',
                'theme_accent' => '#FED7AA',
                'theme_sidebar_start' => '#7C2D12',
                'theme_sidebar_end' => '#431407',
            ])
            ->assertSessionHasErrors('theme_primary');

        $this->assertDatabaseMissing('settings', ['key' => 'theme_primary']);
    }

    public function test_non_admin_cannot_update_global_brand_colors(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->post(route('admin.settings.theme.update'), [
                'theme_primary' => '#C2410C',
                'theme_secondary' => '#9A3412',
                'theme_accent' => '#FED7AA',
                'theme_sidebar_start' => '#7C2D12',
                'theme_sidebar_end' => '#431407',
            ])
            ->assertForbidden();

        $this->assertNull(Setting::value('theme_primary'));
    }
}
