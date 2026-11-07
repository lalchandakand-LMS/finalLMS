<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_cannot_demote_themselves_and_lock_out_the_platform(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.updateRole', $admin), ['role' => 'student'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_branch_manager_role_requires_an_active_branch_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'teacher']);
        $inactiveBranch = Branch::create([
            'name' => 'Inactive Center',
            'slug' => 'inactive-center',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.updateRole', $staff), [
                'role' => 'branch_manager',
                'branch_id' => $inactiveBranch->id,
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'role' => 'teacher',
            'branch_id' => null,
        ]);
    }

    public function test_branch_assignment_is_saved_for_branch_manager_and_cleared_when_role_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'teacher']);
        $branch = Branch::create([
            'name' => 'Active Center',
            'slug' => 'active-center',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.updateRole', $staff), [
                'role' => 'branch_manager',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
        ]);

        $this->post(route('admin.users.updateRole', $staff), ['role' => 'accountant'])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'role' => 'accountant',
            'branch_id' => null,
        ]);
    }

    public function test_branch_manager_cannot_be_assigned_without_a_branch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.updateRole', $staff), ['role' => 'branch_manager'])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'role' => 'teacher',
            'branch_id' => null,
        ]);
    }
}
