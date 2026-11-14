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
        $accountant = User::factory()->create(['role' => 'accountant', 'branch_id' => $branch->id, 'admin_permissions' => ['finance.invoices', 'finance.refunds', 'finance.reports', 'finance.expenses']]);
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

        $this->actingAs($accountant)->get(route('admin.finance.expenses.index'))
            ->assertOk()
            ->assertSee('Local stationery')
            ->assertDontSee('Remote stationery');

        $this->post(route('admin.finance.expenses.store'), [
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

        $this->delete(route('admin.finance.expenses.destroy', $remoteExpense))->assertNotFound();
        $this->delete(route('admin.finance.expenses.destroy', $localExpense))->assertRedirect();
        $this->assertDatabaseMissing('expenses', ['id' => $localExpense->id]);
        $this->assertDatabaseHas('expenses', ['id' => $remoteExpense->id]);
    }

    public function test_accountant_without_active_branch_cannot_access_expenses(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $this->actingAs($accountant)->get(route('admin.finance.expenses.index'))->assertForbidden();
    }

    public function test_inactive_branch_blocks_accountant_expense_reads_and_mutations(): void
        {
            $branch = Branch::create(['name' => 'Closing Branch', 'slug' => 'closing-branch', 'is_active' => true]);
            $accountant = User::factory()->create(['role' => 'accountant', 'admin_permissions' => ['finance.expenses'], 'branch_id' => $branch->id]);
            $expense = Expense::create([
                'branch_id' => $branch->id,
                'created_by' => $accountant->id,
                'category' => 'Utilities',
                'title' => 'Existing branch expense',
                'amount' => 450,
                'payment_method' => 'cash',
                'expense_date' => now()->toDateString(),
            ]);
    
            $branch->update(['is_active' => false]);
    
            $this->actingAs($accountant)->get(route('admin.finance.expenses.index'))->assertForbidden();
            $this->post(route('admin.finance.expenses.store'), [
                'category' => 'Travel',
                'title' => 'Should not be recorded',
                'amount' => 250,
                'payment_method' => 'cash',
                'expense_date' => now()->toDateString(),
            ])->assertForbidden();
            $this->put(route('admin.finance.expenses.update', $expense), [
                'category' => 'Utilities',
                'title' => 'Should not be updated',
                'amount' => 1,
                'payment_method' => 'cash',
                'expense_date' => now()->toDateString(),
            ])->assertNotFound();
            $this->delete(route('admin.finance.expenses.destroy', $expense))->assertNotFound();
    
            $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'title' => 'Existing branch expense', 'amount' => 450]);
            $this->assertDatabaseMissing('expenses', ['title' => 'Should not be recorded']);
        }
}
