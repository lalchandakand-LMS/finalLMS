<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_attendance_audit_and_export_filtered_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureModuleEnabled::class,
        ])->actingAs($admin)
            ->get('/admin/attendance-audit')
            ->assertOk()
            ->assertSee('Attendance audit history');

        $this->actingAs($admin)
            ->get('/admin/attendance-audit/export?source=qr')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('cache-control', 'no-store, private')
            ->assertDownload();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'attendance.audit.exported',
        ]);
    }
}
