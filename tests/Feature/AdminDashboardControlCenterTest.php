<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardControlCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_live_enrollment_and_payment_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        Payment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'amount' => 1250,
            'currency' => 'BDT',
            'transaction_reference' => 'DASHBOARD-METRIC-001',
            'status' => 'approved',
        ]);

        Payment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'nagad',
            'amount' => 500,
            'currency' => 'BDT',
            'transaction_reference' => 'DASHBOARD-METRIC-002',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Active enrollments')
            ->assertSee('New this month')
            ->assertSee('Pending payments')
            ->assertSee('Approved course payments')
            ->assertSee('৳1,250.00');
    }

    public function test_admin_can_view_dashboard_control_center(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        Setting::setValue('dashboard_show_quick_actions', '1');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Academy overview')
            ->assertSee('Users')
            ->assertSee('Courses')
            ->assertSee('Settings')
            ->assertSee('View analytics')
            ->assertSee('Analytics overview')
            ->assertSee('Course progress')
            ->assertSee('Upcoming classes')
            ->assertSee('Top students')
            ->assertSee('Total batches');
    }
}
