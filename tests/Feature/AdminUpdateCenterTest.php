<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUpdateCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_update_center_and_database_migration_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSee('System Update Center')
            ->assertSee('Database migration status')
            ->assertSee('Application code update');
    }

    public function test_student_cannot_access_update_center_or_run_migrations(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.updates.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('admin.updates.migrate'), [
                'confirmation' => 'MIGRATE',
                'password' => 'password',
            ])
            ->assertForbidden();
    }
}
