<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\HomepageStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomepageStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('homepage.statistics.v1');
    }

    public function test_public_statistics_use_real_published_and_enrollment_data(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['status' => 'published']);
        $secondActiveCourse = Course::factory()->create(['status' => 'published']);
        $completedCourse = Course::factory()->create(['status' => 'published']);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $secondActiveCourse->id,
            'status' => 'active',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $completedCourse->id,
            'status' => 'completed',
        ]);

        Lesson::create([
            'course_id' => $course->id,
            'title' => 'Introduction',
            'content' => 'First lesson content',
            'duration_minutes' => 15,
            'order' => 1,
        ]);

        Certificate::create([
            'user_id' => $student->id,
            'course_id' => $completedCourse->id,
            'certificate_number' => 'AEC-TEST-0001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $branch = Branch::create([
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'is_active' => true,
        ]);
        Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $course->teacher_id,
            'title' => 'Morning Batch',
            'capacity' => 20,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonths(3),
            'fee' => 3000,
            'status' => 'open',
        ]);

        $statistics = app(HomepageStatistics::class)->get();

        $this->assertSame(1, $statistics['students']);
        $this->assertSame(3, $statistics['teachers']);
        $this->assertSame(3, $statistics['courses']);
        $this->assertSame(1, $statistics['lessons']);
        $this->assertSame(2, $statistics['enrollments']);
        $this->assertSame(1, $statistics['enrolled_students']);
        $this->assertSame(1, $statistics['completed_enrollments']);
        $this->assertSame(1, $statistics['open_batches']);
        $this->assertSame(1, $statistics['certificates']);
        $this->assertSame(1, $statistics['new_students_this_month']);
    }

    public function test_homepage_renders_the_live_statistics_section(): void
    {
        Cache::forget('homepage.statistics.v1');

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee(__('Live platform statistics'))
            ->assertSee(__('Active enrollments'))
            ->assertSee(__('Open batches'))
            ->assertSee(__('Certificates issued'))
            ->assertSee(__('Completed courses'));
    }
}
