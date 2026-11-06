<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRoleScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_can_only_view_batches_from_their_assigned_branch(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'English Basics',
            'slug' => 'english-basics',
            'description' => 'Course description',
            'price' => 1000,
            'status' => 'published',
        ]);
        $assigned = Branch::create(['name' => 'Assigned Branch', 'slug' => 'assigned-branch']);
        $other = Branch::create(['name' => 'Other Branch', 'slug' => 'other-branch']);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $assigned->id]);

        foreach ([[$assigned, 'Visible Batch'], [$other, 'Hidden Batch']] as [$branch, $title]) {
            Batch::create([
                'branch_id' => $branch->id,
                'course_id' => $course->id,
                'teacher_id' => $teacher->id,
                'title' => $title,
                'capacity' => 20,
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'fee' => 1000,
                'status' => 'open',
            ]);
        }

        $this->actingAs($manager)
            ->get(route('branch-manager.batches.index'))
            ->assertOk()
            ->assertSee('Visible Batch')
            ->assertDontSee('Hidden Batch');

        $this->actingAs($manager)
            ->get(route('admin.batches.index'))
            ->assertForbidden();
    }

    public function test_accountant_can_manage_invoice_ledger_but_cannot_open_admin_payment_review(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($accountant)
            ->get(route('accountant.invoices.index'))
            ->assertOk()
            ->assertSee('Invoices & installments', false);

        $this->post(route('accountant.invoices.store'), [
            'user_id' => $student->id,
            'title' => 'Tuition installment',
            'amount' => 1200,
        ])->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'user_id' => $student->id,
            'title' => 'Tuition installment',
            'created_by' => $accountant->id,
        ]);

        $this->get(route('admin.payments.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
    }
}
