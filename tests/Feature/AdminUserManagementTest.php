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


    public function test_admin_can_assign_an_accountant_to_a_specific_branch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $accountant = User::factory()->create(['role' => 'student']);
        $branch = \App\Models\Branch::create([
            'name' => 'Finance Branch',
            'slug' => 'finance-branch',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.updateRole', $accountant), [
                'role' => 'accountant',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $accountant->id,
            'role' => 'accountant',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_accountant_must_be_assigned_an_active_branch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $accountant = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.updateRole', $accountant), [
                'role' => 'accountant',
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseHas('users', [
            'id' => $accountant->id,
            'role' => 'student',
            'branch_id' => null,
        ]);
    }


    public function test_admin_can_assign_a_student_to_an_active_branch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $branch = \App\Models\Branch::create([
            'name' => 'Student Learning Branch',
            'slug' => 'student-learning-branch',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.updateRole', $student), [
                'role' => 'student',
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'role' => 'student',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_student_cannot_be_assigned_to_an_inactive_branch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $branch = \App\Models\Branch::create([
            'name' => 'Closed Student Branch',
            'slug' => 'closed-student-branch',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.updateRole', $student), [
                'role' => 'student',
                'branch_id' => $branch->id,
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'role' => 'student',
            'branch_id' => null,
        ]);
    }


    public function test_admin_can_create_teacher_and_student_accounts_with_branch_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = \App\Models\Branch::create([
            'name' => 'Central Learning Branch',
            'slug' => 'central-learning-branch',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Teacher',
            'email' => 'new.teacher@example.test',
            'phone_number' => '01700000000',
            'role' => 'teacher',
            'password' => 'SecurePass!2026',
            'password_confirmation' => 'SecurePass!2026',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.teacher@example.test',
            'role' => 'teacher',
        ]);
        $this->assertNotNull(User::where('email', 'new.teacher@example.test')->value('email_verified_at'));

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Student',
            'email' => 'new.student@example.test',
            'role' => 'student',
            'branch_id' => $branch->id,
            'password' => 'SecurePass!2026',
            'password_confirmation' => 'SecurePass!2026',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.student@example.test',
            'role' => 'student',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_admin_can_edit_user_profile_and_change_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->put(route('admin.users.update', $student), [
            'name' => 'Updated Student Name',
            'email' => $student->email,
            'phone_number' => '01800000000',
            'role' => 'teacher',
            'password' => 'NewSecurePass!2026',
            'password_confirmation' => 'NewSecurePass!2026',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Updated Student Name',
            'role' => 'teacher',
            'phone_number' => '01800000000',
        ]);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewSecurePass!2026', $student->fresh()->password));
    }

    public function test_admin_can_soft_delete_user_without_physically_removing_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $student))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $student->id]);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'email' => $student->email]);
    }

    public function test_admin_cannot_delete_or_demote_the_last_administrator(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'deleted_at' => null]);

        $this->post(route('admin.users.updateRole', $admin), ['role' => 'student'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }


    public function test_admin_can_restore_a_soft_deleted_account_from_trash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $student->delete();

        $this->actingAs($admin)->get(route('admin.users.trash'))
            ->assertOk()
            ->assertSee($student->email);

        $this->post(route('admin.users.restore', $student->id))
            ->assertRedirect(route('admin.users.trash'));

        $this->assertDatabaseHas('users', ['id' => $student->id, 'deleted_at' => null]);
    }

    public function test_admin_can_upload_a_profile_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/2ioAAAAASUVORK5CYII=', true);
        $photo = \Illuminate\Http\UploadedFile::fake()->createWithContent('profile.png', $png);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Photo Student',
            'email' => 'photo.student@example.test',
            'role' => 'student',
            'password' => 'SecurePass!2026',
            'password_confirmation' => 'SecurePass!2026',
            'profile_photo' => $photo,
        ])->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'photo.student@example.test')->firstOrFail();
        $this->assertNotNull($created->profile_photo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($created->profile_photo_path);
    }


    public function test_admin_cannot_delete_a_branch_that_has_assigned_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = \App\Models\Branch::create([
            'name' => 'Assigned Branch',
            'slug' => 'assigned-branch',
            'is_active' => true,
        ]);
        User::factory()->create([
            'role' => 'teacher',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.branches.destroy', $branch))
            ->assertRedirect();

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }

}
