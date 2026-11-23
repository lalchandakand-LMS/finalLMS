<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
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
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'status' => 'published',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $statistics = app(HomepageStatistics::class)->get();

        $this->assertSame(1, $statistics['students']);
        $this->assertSame(1, $statistics['teachers']);
        $this->assertSame(1, $statistics['courses']);
        $this->assertSame(1, $statistics['enrollments']);
        $this->assertSame(1, $statistics['enrolled_students']);
    }

    public function test_homepage_renders_the_live_statistics_section(): void
    {
        Cache::forget('homepage.statistics.v1');

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee(__('Live platform statistics'))
            ->assertSee(__('Active enrollments'))
            ->assertSee(__('Open batches'))
            ->assertSee(__('Certificates issued'));
    }
}
