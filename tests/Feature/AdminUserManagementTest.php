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
}
