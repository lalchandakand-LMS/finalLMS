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

    public function test_branch_and_finance_staff_have_separate_scoped_workspaces(): void
    {
        $branch = Branch::create(['name' => 'Workspace Branch', 'slug' => 'workspace-branch']);
        $manager = User::factory()->create(['role' => 'branch_manager',
            'admin_permissions' => ['branch.batches', 'branch.students'], 'branch_id' => $branch->id]);
        $accountant = User::factory()->create(['role' => 'accountant',
            'admin_permissions' => ['finance.invoices', 'finance.refunds', 'finance.reports', 'finance.expenses'], 'branch_id' => $branch->id]);

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('admin-sidebar', false)
            ->assertSee('Branch Panel')
            ->assertSee('Branch dashboard')
            ->assertSee('My branch batches')
            ->assertSee('/branch/batches')
            ->assertDontSee('Finance workspace')
            ->assertDontSee('Admin users');

        $this->actingAs($accountant)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('admin-sidebar', false)
            ->assertSee('Finance workspace')
            ->assertSee('Branch invoices')
            ->assertDontSee('Branch workspace')
            ->assertDontSee('Admin users');

        $this->actingAs($manager)->get(route('admin.finance.invoices.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('admin.branch-workspace.batches.index'))->assertForbidden();
    }

    public function test_teacher_student_and_guardian_have_distinct_portal_navigation(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Teacher Panel')
            ->assertSee('My batches &amp; classes')
            ->assertSee('/teacher/batches');

        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Student Panel')
            ->assertSee('My courses')
            ->assertSee('Guardian requests')
            ->assertSee('/my/courses');

        $guardian = User::factory()->create(['role' => 'guardian']);
        $this->actingAs($guardian)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Guardian Panel')
            ->assertSee('Open guardian portal')
            ->assertSee('/guardian/dashboard');
    }

    public function test_staff_subroles_with_inactive_branch_assignments_are_denied(): void
    {
        $branch = Branch::create(['name' => 'Inactive Branch', 'slug' => 'inactive-branch', 'is_active' => false]);
        $manager = User::factory()->create(['role' => 'branch_manager',
            'admin_permissions' => ['branch.batches', 'branch.students'], 'branch_id' => $branch->id]);
        $accountant = User::factory()->create(['role' => 'accountant',
            'admin_permissions' => ['finance.invoices', 'finance.refunds', 'finance.reports', 'finance.expenses'], 'branch_id' => $branch->id]);

        $this->actingAs($manager)->get(route('admin.branch-workspace.batches.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('admin.finance.invoices.index'))->assertForbidden();
    }

    public function test_legacy_staff_urls_redirect_into_their_dedicated_workspaces(): void
    {
        $branch = Branch::create(['name' => 'Legacy Branch', 'slug' => 'legacy-branch']);
        $manager = User::factory()->create(['role' => 'branch_manager',
            'admin_permissions' => ['branch.batches', 'branch.students'], 'branch_id' => $branch->id]);
        $accountant = User::factory()->create(['role' => 'accountant',
            'admin_permissions' => ['finance.invoices', 'finance.refunds', 'finance.reports', 'finance.expenses'], 'branch_id' => $branch->id]);

        $this->actingAs($manager)->get('/branch-manager/batches')
            ->assertRedirect(route('branch.batches.index'));
        $this->actingAs($accountant)->get('/accountant/invoices')
            ->assertRedirect(route('admin.finance.invoices.index'));
        $this->actingAs($accountant)->get('/accountant/finance-reports')
            ->assertRedirect(route('admin.finance.reports.index'));
    }

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
        $manager = User::factory()->create(['role' => 'branch_manager',
            'admin_permissions' => ['branch.batches', 'branch.students'], 'branch_id' => $assigned->id]);

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
            ->get(route('admin.branch-workspace.batches.index'))
            ->assertOk()
            ->assertSee('Visible Batch')
            ->assertDontSee('Hidden Batch');

        $this->actingAs($manager)
            ->get(route('admin.batches.index'))
            ->assertForbidden();
    }

    public function test_branch_manager_can_create_and_update_only_batches_in_their_branch(): void
    {
        $assigned = Branch::create(['name' => 'Manager Branch', 'slug' => 'manager-branch']);
        $teacher = User::factory()->create(['role' => 'teacher', 'branch_id' => $assigned->id]);
        $course = Course::create(['teacher_id' => $teacher->id, 'title' => 'Branch English', 'slug' => 'branch-english', 'description' => 'Branch course', 'price' => 1000, 'status' => 'published']);
        $other = Branch::create(['name' => 'Foreign Branch', 'slug' => 'foreign-branch']);
        $manager = User::factory()->create(['role' => 'branch_manager',
            'admin_permissions' => ['branch.batches', 'branch.students'], 'branch_id' => $assigned->id]);
        $foreignBatch = Batch::create(['branch_id' => $other->id, 'course_id' => $course->id, 'teacher_id' => $teacher->id, 'title' => 'Foreign Batch', 'capacity' => 20, 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'fee' => 1000, 'status' => 'open']);
        $payload = ['course_id' => $course->id, 'teacher_id' => $teacher->id, 'title' => 'New Local Batch', 'capacity' => 15, 'starts_at' => now()->addDay()->format('Y-m-d H:i:s'), 'ends_at' => now()->addMonth()->format('Y-m-d H:i:s'), 'fee' => 1200];
        $this->actingAs($manager)->post(route('admin.branch-workspace.batches.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('batches', ['title' => 'New Local Batch', 'branch_id' => $assigned->id]);
        $this->put(route('admin.branch-workspace.batches.update', $foreignBatch), ['title' => 'Tampered', 'capacity' => 20, 'starts_at' => now()->addDay(), 'ends_at' => now()->addMonth(), 'fee' => 1000, 'status' => 'closed'])->assertNotFound();
        $this->assertDatabaseHas('batches', ['id' => $foreignBatch->id, 'title' => 'Foreign Batch']);
    }

    public function test_accountant_can_manage_invoice_ledger_but_cannot_open_admin_payment_review(): void
    {
        $branch = Branch::create(['name' => 'Accountant Branch', 'slug' => 'accountant-branch']);
        $accountant = User::factory()->create(['role' => 'accountant',
            'admin_permissions' => ['finance.invoices', 'finance.refunds', 'finance.reports', 'finance.expenses'], 'branch_id' => $branch->id]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);

        $this->actingAs($accountant)
            ->get(route('admin.finance.invoices.index'))
            ->assertOk()
            ->assertSee('Invoices & installments')
            ->assertSee('/admin/finance/invoices')
            ->assertDontSee('Online payment review');

        $this->post(route('admin.finance.invoices.store'), [
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
