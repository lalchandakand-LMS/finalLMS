<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_and_paginate_large_user_lists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        User::factory()->count(26)->create(['role' => 'student']);
        $target = User::factory()->create([
            'role' => 'student',
            'name' => 'Special Search Student',
            'email' => 'special-search@example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'special-search']))
            ->assertOk()
            ->assertSee('special-search@example.test')
            ->assertDontSee('Showing 1–25 of 27 accounts');

        $this->get(route('admin.users.index', ['role' => 'student', 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 26–27 of 27 accounts');
    }

    public function test_admin_can_update_user_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.updateRole', $student), [
                'role' => 'teacher',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'role' => 'teacher',
        ]);
    }


    public function test_admin_can_assign_branch_manager_to_a_specific_branch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'student']);
        $branch = \App\Models\Branch::create([
            'name' => 'North Branch',
            'slug' => 'north-branch',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.updateRole', $manager), [
                'role' => 'branch_manager',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $manager->id,
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_branch_manager_must_be_assigned_a_branch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.updateRole', $manager), [
                'role' => 'branch_manager',
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseHas('users', [
            'id' => $manager->id,
            'role' => 'student',
            'branch_id' => null,
        ]);
    }



    public function test_inactive_branch_cannot_be_assigned_to_a_branch_manager(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'student']);
        $branch = \App\Models\Branch::create([
            'name' => 'Inactive Branch',
            'slug' => 'inactive-branch',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.updateRole', $manager), [
                'role' => 'branch_manager',
                'branch_id' => $branch->id,
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseHas('users', [
            'id' => $manager->id,
            'role' => 'student',
            'branch_id' => null,
        ]);
    }

}
