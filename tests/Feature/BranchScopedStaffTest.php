<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchScopedStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_can_only_create_batches_with_published_courses_and_teachers_from_their_branch(): void
    {
        $branch = Branch::create(['name' => 'Dhaka Branch', 'slug' => 'dhaka-branch']);
        $otherBranch = Branch::create(['name' => 'Chattogram Branch', 'slug' => 'chattogram-branch']);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);
        $localTeacher = User::factory()->create(['role' => 'teacher', 'branch_id' => $branch->id]);
        $remoteTeacher = User::factory()->create(['role' => 'teacher', 'branch_id' => $otherBranch->id]);
        $localCourse = Course::factory()->create(['status' => 'published']);
        $unpublishedCourse = Course::factory()->create(['status' => 'draft']);

        $base = [
            'title' => 'Weekend English',
            'capacity' => 20,
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'fee' => 2500,
        ];

        $this->actingAs($manager)->post(route('branch-manager.batches.store'), [
            ...$base, 'course_id' => $localCourse->id, 'teacher_id' => $remoteTeacher->id,
        ])->assertSessionHasErrors('teacher_id');

        $this->post(route('branch-manager.batches.store'), [
            ...$base, 'course_id' => $unpublishedCourse->id, 'teacher_id' => $localTeacher->id,
        ])->assertSessionHasErrors('course_id');

        $this->post(route('branch-manager.batches.store'), [
            ...$base, 'course_id' => $localCourse->id, 'teacher_id' => $localTeacher->id,
        ])->assertRedirect(route('branch-manager.batches.index'));

        $this->assertDatabaseHas('batches', [
            'branch_id' => $branch->id,
            'teacher_id' => $localTeacher->id,
            'course_id' => $localCourse->id,
            'title' => 'Weekend English',
        ]);
    }

    public function test_accountant_only_sees_and_receives_payments_for_invoices_in_their_branch(): void
    {
        $branch = Branch::create(['name' => 'Dhaka Branch', 'slug' => 'dhaka-branch']);
        $otherBranch = Branch::create(['name' => 'Chattogram Branch', 'slug' => 'chattogram-branch']);
        $accountant = User::factory()->create(['role' => 'accountant', 'branch_id' => $branch->id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $localStudent = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $remoteStudent = User::factory()->create(['role' => 'student', 'branch_id' => $otherBranch->id]);
        $localInvoice = Invoice::create([
            'user_id' => $localStudent->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-DHK-0001', 'title' => 'Dhaka tuition',
            'amount' => 1200, 'status' => 'unpaid',
        ]);
        $remoteInvoice = Invoice::create([
            'user_id' => $remoteStudent->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-CTG-0001', 'title' => 'Chattogram tuition',
            'amount' => 1500, 'status' => 'unpaid',
        ]);

        $this->actingAs($accountant)->get(route('accountant.invoices.index'))
            ->assertOk()
            ->assertSee($localInvoice->invoice_number)
            ->assertDontSee($remoteInvoice->invoice_number);

        $this->post(route('accountant.invoices.payments.store', $remoteInvoice), [
            'amount' => 100, 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s'),
        ])->assertNotFound();

        $this->assertDatabaseCount('invoice_payments', 0);
    }

    public function test_accountant_cannot_create_invoice_for_local_student_using_another_branches_batch(): void
    {
        $localBranch = Branch::create(['name' => 'Local Branch', 'slug' => 'local-branch']);
        $remoteBranch = Branch::create(['name' => 'Remote Branch', 'slug' => 'remote-branch']);
        $accountant = User::factory()->create(['role' => 'accountant', 'branch_id' => $localBranch->id]);
        $localStudent = User::factory()->create(['role' => 'student', 'branch_id' => $localBranch->id]);
        $remoteTeacher = User::factory()->create(['role' => 'teacher', 'branch_id' => $remoteBranch->id]);
        $course = Course::factory()->create(['teacher_id' => $remoteTeacher->id, 'status' => 'published']);
        $remoteBatch = Batch::create([
            'branch_id' => $remoteBranch->id,
            'course_id' => $course->id,
            'teacher_id' => $remoteTeacher->id,
            'title' => 'Remote batch',
            'capacity' => 20,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'fee' => 1000,
            'status' => 'open',
        ]);

        $this->actingAs($accountant)->post(route('accountant.invoices.store'), [
            'user_id' => $localStudent->id,
            'batch_id' => $remoteBatch->id,
            'title' => 'Invalid cross-branch invoice',
            'amount' => 1000,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('invoices', ['title' => 'Invalid cross-branch invoice']);
    }

}
