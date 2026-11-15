<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DashboardAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchNoticeTargetingTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'is_active' => true,
        ]);
    }

    public function test_branch_specific_notice_is_only_visible_to_users_in_that_branch(): void
    {
        $rangpur = $this->branch('Rangpur');
        $dhaka = $this->branch('Dhaka');
        $rangpurStudent = User::factory()->create(['role' => 'student', 'branch_id' => $rangpur->id]);
        $dhakaStudent = User::factory()->create(['role' => 'student', 'branch_id' => $dhaka->id]);

        DashboardAnnouncement::create([
            'title' => 'Rangpur branch notice',
            'content_type' => 'custom',
            'audience' => 'student',
            'branch_id' => $rangpur->id,
            'is_active' => true,
        ]);

        $this->actingAs($rangpurStudent)->get(route('dashboard'))
            ->assertOk()->assertSee('Rangpur branch notice');

        $this->actingAs($dhakaStudent)->get(route('dashboard'))
            ->assertOk()->assertDontSee('Rangpur branch notice');
    }

    public function test_global_notice_remains_visible_across_branches(): void
    {
        $rangpur = $this->branch('Rangpur');
        $dhaka = $this->branch('Dhaka');
        $rangpurStudent = User::factory()->create(['role' => 'student', 'branch_id' => $rangpur->id]);
        $dhakaStudent = User::factory()->create(['role' => 'student', 'branch_id' => $dhaka->id]);

        DashboardAnnouncement::create([
            'title' => 'Academy-wide notice',
            'content_type' => 'custom',
            'audience' => 'all',
            'is_active' => true,
        ]);

        $this->actingAs($rangpurStudent)->get(route('dashboard'))->assertSee('Academy-wide notice');
        $this->actingAs($dhakaStudent)->get(route('dashboard'))->assertSee('Academy-wide notice');
    }

    public function test_branch_manager_can_be_selected_as_notice_audience(): void
    {
        $branch = $this->branch('Rangpur');
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);

        DashboardAnnouncement::create([
            'title' => 'Branch manager operations notice',
            'content_type' => 'custom',
            'audience' => 'branch_manager',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()->assertSee('Branch manager operations notice');
    }

    public function test_admin_can_create_a_branch_targeted_notice(): void
    {
        $branch = $this->branch('Rangpur');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.dashboard-announcements.store'), [
            'title' => 'Rangpur exam notice',
            'content_type' => 'custom',
            'audience' => 'student',
            'branch_id' => $branch->id,
            'priority' => 10,
            'is_active' => '1',
        ])->assertRedirect(route('admin.dashboard-announcements.index'));

        $this->assertDatabaseHas('dashboard_announcements', [
            'title' => 'Rangpur exam notice',
            'branch_id' => $branch->id,
            'audience' => 'student',
            'is_active' => 1,
        ]);
    }
}
