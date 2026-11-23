<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_activate_deactivate_suspend_and_control_branch_registration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.branches.store'), [
            'name' => 'Rajshahi Center',
            'address' => 'Rajshahi',
            'phone' => '01700000000',
            'status' => 'active',
            'registration_open' => '1',
        ])->assertRedirect(route('admin.batches.index'));

        $branch = Branch::query()->where('name', 'Rajshahi Center')->firstOrFail();
        $this->assertSame('active', $branch->status);
        $this->assertTrue($branch->is_active);
        $this->assertTrue($branch->registration_open);

        foreach ([
            ['status' => 'inactive', 'registration_open' => '0', 'expected_active' => false],
            ['status' => 'suspended', 'registration_open' => '1', 'expected_active' => false],
            ['status' => 'active', 'registration_open' => '1', 'expected_active' => true],
        ] as $state) {
            $this->put(route('admin.branches.update', $branch), [
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'status' => $state['status'],
                'registration_open' => $state['registration_open'],
            ])->assertRedirect(route('admin.batches.index'));

            $branch->refresh();
            $this->assertSame($state['status'], $branch->status);
            $this->assertSame($state['expected_active'], $branch->is_active);
            $this->assertSame($state['registration_open'] === '1', $branch->registration_open);
        }
    }

    public function test_non_admin_cannot_manage_branch_lifecycle(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $branch = Branch::query()->create(['name' => 'Protected Center', 'slug' => 'protected-center']);

        $this->actingAs($teacher)->put(route('admin.branches.update', $branch), [
            'name' => 'Changed Center',
            'status' => 'suspended',
            'registration_open' => '0',
        ])->assertForbidden();
    }
}
