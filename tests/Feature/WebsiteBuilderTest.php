<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_and_render_safe_homepage_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'sections' => [
                [
                    'title' => 'Our learning approach',
                    'body' => '<script>alert(1)</script>Learn with confidence.',
                    'button_label' => 'Browse courses',
                    'button_url' => 'https://example.com/courses',
                    'enabled' => '1',
                ],
            ],
        ])->assertRedirect(route('admin.website-builder.index'));

        $this->assertDatabaseHas('settings', ['key' => 'website_sections']);
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('Learn with confidence.');
    }

    public function test_non_admin_cannot_access_website_builder(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.website-builder.index'))->assertForbidden();
    }
}
