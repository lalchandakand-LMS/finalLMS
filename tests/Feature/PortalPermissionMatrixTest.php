<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_staff_and_learning_portal_is_exclusive_to_its_authorized_role(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Permission Matrix Branch',
            'slug' => 'permission-matrix-branch',
            'is_active' => true,
        ]);

        $portals = [
            'admin' => [
                'route' => 'admin.cms.index',
                'attributes' => ['role' => 'admin'],
            ],
            'branch_manager' => [
                'route' => 'branch.dashboard',
                'attributes' => [
                    'role' => 'branch_manager',
                    'branch_id' => $branch->id,
                    'admin_permissions' => ['branch.batches', 'branch.students'],
                ],
            ],
            'teacher' => [
                'route' => 'teacher.batches.index',
                'attributes' => ['role' => 'teacher'],
            ],
            'student' => [
                'route' => 'student.courses.index',
                'attributes' => ['role' => 'student'],
            ],
            'guardian' => [
                'route' => 'guardian.dashboard',
                'attributes' => ['role' => 'guardian'],
            ],
            'accountant' => [
                'route' => 'admin.finance.invoices.index',
                'attributes' => [
                    'role' => 'accountant',
                    'branch_id' => $branch->id,
                    'admin_permissions' => ['finance.invoices'],
                ],
            ],
        ];

        $actors = [];
        foreach ($portals as $role => $portal) {
            $actors[$role] = User::factory()->create(array_merge(
                ['email_verified_at' => now()],
                $portal['attributes'],
            ));
        }

        foreach ($portals as $role => $portal) {
            $this->actingAs($actors[$role])
                ->get(route($portal['route']))
                ->assertOk();

            foreach ($portals as $otherRole => $otherPortal) {
                if ($otherRole === $role) {
                    continue;
                }

                $this->actingAs($actors[$role])
                    ->get(route($otherPortal['route']))
                    ->assertForbidden(
                        sprintf('%s must not access the %s portal.', $role, $otherRole)
                    );
            }
        }
    }
}
