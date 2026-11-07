<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_head_office_and_branch_expenses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::create(['name' => 'Dhaka Branch', 'slug' => 'dhaka-branch']);

        $this->actingAs($admin)->post(route('admin.expenses.store'), [
            'category' => 'Utilities',
            'title' => 'Electricity bill',
            'amount' => 1800,
            'payment_method' => 'bank',
            'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->post(route('admin.expenses.store'), [
            'branch_id' => $branch->id,
            'category' => 'Rent',
            'title' => 'Branch office rent',
            'amount' => 12000,
            'payment_method' => 'bank',
            'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', ['title' => 'Electricity bill', 'branch_id' => null]);
        $this->assertDatabaseHas('expenses', ['title' => 'Branch office rent', 'branch_id' => $branch->id]);
    }

    public function test_accountant_expenses_are_forced_to_own_branch_and_cannot_delete_other_branch_expenses(): void
    {
        $branch = Branch::create(['name' => 'Dhaka Branch', 'slug' => 'dhaka-branch']);
        $otherBranch = Branch::create(['name' => 'Sylhet Branch', 'slug' => 'sylhet-branch']);
        $accountant = User::factory()->create(['role' => 'accountant', 'branch_id' => $branch->id]);
        $localExpense = Expense::create([
            'branch_id' => $branch->id, 'created_by' => $accountant->id,
            'category' => 'Supplies', 'title' => 'Local stationery',
            'amount' => 500, 'payment_method' => 'cash', 'expense_date' => now()->toDateString(),
        ]);
        $remoteExpense = Expense::create([
            'branch_id' => $otherBranch->id, 'created_by' => $accountant->id,
            'category' => 'Supplies', 'title' => 'Remote stationery',
            'amount' => 900, 'payment_method' => 'cash', 'expense_date' => now()->toDateString(),
        ]);

        $this->actingAs($accountant)->get(route('accountant.expenses.index'))
            ->assertOk()
            ->assertSee('Local stationery')
            ->assertDontSee('Remote stationery');

        $this->post(route('accountant.expenses.store'), [
            'branch_id' => $otherBranch->id,
            'category' => 'Travel',
            'title' => 'Accountant travel',
            'amount' => 700,
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'title' => 'Accountant travel',
            'branch_id' => $branch->id,
        ]);

        $this->delete(route('accountant.expenses.destroy', $remoteExpense))->assertNotFound();
        $this->delete(route('accountant.expenses.destroy', $localExpense))->assertRedirect();
        $this->assertDatabaseMissing('expenses', ['id' => $localExpense->id]);
        $this->assertDatabaseHas('expenses', ['id' => $remoteExpense->id]);
    }

    public function test_accountant_without_active_branch_cannot_access_expenses(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $this->actingAs($accountant)->get(route('accountant.expenses.index'))->assertForbidden();
    }
}
