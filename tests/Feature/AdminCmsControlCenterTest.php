<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCmsControlCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_cms_control_center_and_its_management_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.cms.index'))
            ->assertOk()
            ->assertSee('CMS Control Center')
            ->assertSee('Website management')
            ->assertSee('Appearance & extensions')
            ->assertSee(route('admin.website-builder.index'))
            ->assertSee(route('admin.website-pages.index'))
            ->assertSee(route('admin.website-menu.index'))
            ->assertSee(route('admin.plugins.index'))
            ->assertSee(route('admin.themes.index'));
    }

    public function test_non_admin_users_cannot_access_cms_control_center(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.cms.index'))
            ->assertForbidden();
    }
}
