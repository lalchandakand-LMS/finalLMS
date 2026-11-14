<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModulePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_grant_only_selected_branch_modules_to_a_branch_manager(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Permission Branch', 'slug' => 'permission-branch', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->post(route('admin.users.updateRole', $manager), [
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
            'admin_permissions_present' => '1',
            'admin_permissions' => ['branch.batches'],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame(['branch.batches'], $manager->fresh()->admin_permissions);

        $this->actingAs($manager)->get(route('admin.branch-workspace.batches.index'))->assertOk();
        $this->get(route('admin.branch-workspace.students.index'))->assertForbidden();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My branch batches')
            ->assertDontSee('Branch students');
    }

    public function test_admin_can_grant_and_revoke_accountant_modules_independently(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Finance Permission Branch', 'slug' => 'finance-permission-branch', 'is_active' => true]);
        $accountant = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->post(route('admin.users.updateRole', $accountant), [
            'role' => 'accountant',
            'branch_id' => $branch->id,
            'admin_permissions_present' => '1',
            'admin_permissions' => ['finance.invoices', 'finance.refunds', 'finance.reports', 'finance.expenses'],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame(
            ['finance.invoices', 'finance.refunds', 'finance.reports', 'finance.expenses'],
            $accountant->fresh()->admin_permissions
        );
        $this->actingAs($accountant)->get(route('admin.finance.invoices.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.users.updateRole', $accountant), [
            'role' => 'accountant',
            'branch_id' => $branch->id,
            'admin_permissions_present' => '1',
            'admin_permissions' => ['finance.expenses'],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame(['finance.expenses'], $accountant->fresh()->admin_permissions);

        $this->actingAs($accountant)->get(route('admin.finance.expenses.index'))->assertOk();
        $this->get(route('admin.finance.invoices.index'))->assertForbidden();
        $this->get(route('admin.finance.refunds.index'))->assertForbidden();
        $this->get(route('admin.finance.reports.index'))->assertForbidden();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Branch expenses')
            ->assertDontSee('Branch invoices')
            ->assertDontSee('Refund requests');
    }

    public function test_staff_without_an_explicit_module_grant_is_denied_by_default(): void
    {
        $branch = Branch::create(['name' => 'No Grant Branch', 'slug' => 'no-grant-branch', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);

        $this->actingAs($manager)->get(route('admin.branch-workspace.batches.index'))->assertForbidden();
        $this->assertFalse($manager->hasAdminPermission('branch.batches'));
    }

    public function test_admin_permission_grants_are_filtered_by_staff_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->post(route('admin.users.updateRole', $student), [
            'role' => 'teacher',
            'admin_permissions_present' => '1',
            'admin_permissions' => ['finance.invoices', 'branch.batches'],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame([], $student->fresh()->admin_permissions);
    }

    public function test_root_admin_has_all_admin_module_permissions_without_stored_grants(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'admin_permissions' => null]);

        $this->assertTrue($admin->hasAdminPermission('branch.batches'));
        $this->assertTrue($admin->hasAdminPermission('finance.expenses'));
    }
    public function test_changing_a_staff_role_without_permission_fields_clears_old_module_grants(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Role Change Branch', 'slug' => 'role-change-branch', 'is_active' => true]);
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
            'admin_permissions' => ['branch.batches', 'branch.students'],
        ]);

        $this->actingAs($admin)->post(route('admin.users.updateRole', $manager), [
            'role' => 'teacher',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame([], $manager->fresh()->admin_permissions);
    }

}
