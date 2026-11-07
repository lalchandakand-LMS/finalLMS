<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagerDataScopeTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'slug' => str()->slug($name),
            'is_active' => true,
        ]);
    }

    private function batch(Branch $branch, Course $course, string $title): Batch
    {
        return Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $course->teacher_id,
            'title' => $title,
            'capacity' => 30,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'fee' => 3000,
            'status' => 'open',
        ]);
    }

    public function test_branch_manager_batch_list_contains_only_the_assigned_branch(): void
    {
        $ownBranch = $this->branch('Own Center');
        $otherBranch = $this->branch('Other Center');
        $course = Course::factory()->create();
        $this->batch($ownBranch, $course, 'Own branch batch');
        $this->batch($otherBranch, $course, 'Other branch secret batch');
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $ownBranch->id,
        ]);

        $this->actingAs($manager)
            ->get(route('branch-manager.batches.index'))
            ->assertOk()
            ->assertSee('Own branch batch')
            ->assertDontSee('Other branch secret batch');
    }

    public function test_branch_manager_cannot_update_a_batch_from_another_branch(): void
    {
        $ownBranch = $this->branch('Manager Center');
        $otherBranch = $this->branch('Protected Center');
        $course = Course::factory()->create();
        $foreignBatch = $this->batch($otherBranch, $course, 'Protected batch');
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $ownBranch->id,
        ]);

        $this->actingAs($manager)
            ->put(route('branch-manager.batches.update', $foreignBatch), [
                'title' => 'Unauthorized change',
                'capacity' => 50,
                'starts_at' => now()->addDays(2)->toDateTimeString(),
                'ends_at' => now()->addMonths(2)->toDateTimeString(),
                'fee' => 3500,
                'status' => 'open',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('batches', [
            'id' => $foreignBatch->id,
            'branch_id' => $otherBranch->id,
            'title' => 'Protected batch',
        ]);
    }

    public function test_branch_manager_cannot_choose_another_branch_when_creating_a_batch(): void
    {
        $ownBranch = $this->branch('Assigned Center');
        $otherBranch = $this->branch('Unassigned Center');
        $course = Course::factory()->create();
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $ownBranch->id,
        ]);

        $this->actingAs($manager)
            ->post(route('branch-manager.batches.store'), [
                'branch_id' => $otherBranch->id,
                'course_id' => $course->id,
                'teacher_id' => $course->teacher_id,
                'title' => 'Created in assigned branch',
                'capacity' => 25,
                'starts_at' => now()->addDay()->toDateTimeString(),
                'ends_at' => now()->addMonth()->toDateTimeString(),
                'fee' => 2500,
            ])
            ->assertRedirect(route('branch-manager.batches.index'));

        $this->assertDatabaseHas('batches', [
            'branch_id' => $ownBranch->id,
            'title' => 'Created in assigned branch',
        ]);
        $this->assertDatabaseMissing('batches', [
            'branch_id' => $otherBranch->id,
            'title' => 'Created in assigned branch',
        ]);
    }

    public function test_accountant_cannot_access_admin_or_branch_manager_batch_routes(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);

        $this->actingAs($accountant)
            ->get(route('admin.batches.index'))
            ->assertForbidden();

        $this->get(route('branch-manager.batches.index'))
            ->assertForbidden();
    }

    public function test_branch_manager_cannot_access_accountant_invoice_routes(): void
    {
        $branch = $this->branch('Branch Role Center');
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($manager)
            ->get(route('accountant.invoices.index'))
            ->assertForbidden();
    }
}
