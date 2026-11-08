<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveClassRecordingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_teacher_can_schedule_live_class_for_their_batch(): void
    {
        [$teacher, $batch] = $this->makeBatch();

        $this->actingAs($teacher)
            ->post(route('teacher.batches.live-classes.store', $batch), [
                'title' => 'Spoken English Live Session',
                'description' => 'Speaking practice.',
                'meeting_url' => 'https://meet.example.com/room-123',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('live_classes', [
            'batch_id' => $batch->id,
            'title' => 'Spoken English Live Session',
            'created_by' => $teacher->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_unassigned_teacher_cannot_schedule_or_manage_another_teachers_session(): void
    {
        [$teacher, $batch] = $this->makeBatch();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $session = LiveClass::create([
            'batch_id' => $batch->id,
            'title' => 'Private lesson',
            'meeting_url' => 'https://meet.example.com/private',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => 'scheduled',
            'created_by' => $teacher->id,
        ]);

        $this->actingAs($otherTeacher)
            ->post(route('teacher.batches.live-classes.store', $batch), [
                'title' => 'Unauthorized',
                'meeting_url' => 'https://meet.example.com/unauthorized',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(1)->addHour(),
            ])
            ->assertForbidden();

        $this->put(route('live-classes.update', $session), [
            'title' => 'Changed',
            'meeting_url' => 'https://meet.example.com/changed',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => 'live',
        ])->assertForbidden();
    }

    public function test_student_only_sees_sessions_for_active_enrollments_and_join_link_opens_near_start(): void
    {
        [$teacher, $batch, $course] = $this->makeBatch();
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $session = LiveClass::create([
            'batch_id' => $batch->id,
            'title' => 'Today live lesson',
            'meeting_url' => 'https://meet.example.com/today',
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addMinutes(40),
            'status' => 'live',
            'created_by' => $teacher->id,
        ]);

        $this->actingAs($student)
            ->get(route('live-classes.index'))
            ->assertOk()
            ->assertSee('Today live lesson')
            ->assertSee('https://meet.example.com/today');

        $this->actingAs($otherStudent)
            ->get(route('live-classes.index'))
            ->assertOk()
            ->assertDontSee('Today live lesson')
            ->assertDontSee('https://meet.example.com/today');
    }

    public function test_student_cannot_see_meeting_url_before_join_window_opens(): void
    {
        [$teacher, $batch, $course] = $this->makeBatch();
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);

        LiveClass::create([
            'batch_id' => $batch->id,
            'title' => 'Tomorrow live lesson',
            'meeting_url' => 'https://meet.example.com/tomorrow-secret',
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(4),
            'status' => 'scheduled',
            'created_by' => $teacher->id,
        ]);

        $this->actingAs($student)
            ->get(route('live-classes.index'))
            ->assertOk()
            ->assertSee('Tomorrow live lesson')
            ->assertDontSee('https://meet.example.com/tomorrow-secret');
    }

    public function test_enrolled_student_can_watch_published_unlisted_youtube_recording(): void
    {
        [$teacher, $batch, $course] = $this->makeBatch();
        $student = User::factory()->create(['role' => 'student']);
        $outsider = User::factory()->create(['role' => 'student']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);
        LiveClass::create([
            'batch_id' => $batch->id,
            'title' => 'Recorded pronunciation class',
            'meeting_url' => 'https://meet.example.com/pronunciation',
            'recording_url' => 'https://youtu.be/abcdefghijk',
            'recording_published_at' => now()->subMinute(),
            'recording_notes' => 'Practice the examples.',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHour(),
            'status' => 'completed',
            'created_by' => $teacher->id,
        ]);
        $this->actingAs($student)->get(route('live-classes.index'))->assertOk()
            ->assertSee('https://www.youtube-nocookie.com/embed/abcdefghijk', false)
            ->assertSee('Practice the examples.');
        $this->actingAs($outsider)->get(route('live-classes.index'))->assertOk()
            ->assertDontSee('Recorded pronunciation class')
            ->assertDontSee('https://www.youtube-nocookie.com/embed/abcdefghijk', false);
    }

    public function test_future_recording_publication_does_not_embed_video_for_student(): void
    {
        [$teacher, $batch, $course] = $this->makeBatch();
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);
        LiveClass::create([
            'batch_id' => $batch->id,
            'title' => 'Scheduled recording release',
            'meeting_url' => 'https://meet.example.com/release',
            'recording_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'recording_published_at' => now()->addDay(),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHour(),
            'status' => 'completed',
            'created_by' => $teacher->id,
        ]);
        $this->actingAs($student)->get(route('live-classes.index'))->assertOk()
            ->assertDontSee('youtube-nocookie.com/embed/abcdefghijk', false);
    }

    private function makeBatch(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'English Speaking',
            'slug' => 'english-speaking-'.uniqid(),
            'description' => 'Practice.',
            'price' => 1000,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Main Center', 'slug' => 'main-center-'.uniqid()]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Evening Batch',
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 1000,
            'status' => 'open',
        ]);

        return [$teacher, $batch, $course];
    }
}
