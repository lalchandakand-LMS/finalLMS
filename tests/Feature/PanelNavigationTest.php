<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_authenticated_role_gets_its_own_left_navigation(): void
    {
        $cases = [
            ['student', 'My learning', 'My courses'],
            ['teacher', 'Teaching tools', 'Assigned courses'],
            ['branch_manager', 'Branch management', 'Branch batches'],
            ['accountant', 'Finance workspace', 'Invoices &amp; fees'],
        ];

        foreach ($cases as [$role, $section, $link]) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('app-sidebar')
                ->assertSee($section)
                ->assertSee($link)
                ->assertDontSee('Admin workspace');
        }
    }

    public function test_guardian_gets_family_navigation_and_only_guardian_dashboard(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian']);

        $this->actingAs($guardian)
            ->get(route('dashboard'))
            ->assertRedirect(route('guardian.dashboard'));

        $this->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('app-sidebar')
            ->assertSee('Family workspace')
            ->assertSee('My children')
            ->assertDontSee('Admin workspace');
    }

    public function test_admin_gets_full_administration_navigation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Admin workspace')
            ->assertSee('Academy management')
            ->assertSee('Finance &amp; payments')
            ->assertSee('Website & system')
            ->assertSee('Settings');

        $this->get(route('admin.settings.index'))->assertOk()->assertSee('Custom color system');
    }
}
