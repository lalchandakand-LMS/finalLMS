<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GuardianStudentLink;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveClassAccessTest extends TestCase
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

    public function test_guardian_sees_only_currently_linked_students_sessions_and_never_the_meeting_url(): void
    {
        [$teacher, $batch, $course] = $this->makeBatch();
        $guardian = User::factory()->create(['role' => 'guardian', 'branch_id' => $batch->branch_id]);
        $linkedStudent = User::factory()->create(['role' => 'student', 'branch_id' => $batch->branch_id]);
        $unlinkedStudent = User::factory()->create(['role' => 'student', 'branch_id' => $batch->branch_id]);

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $linkedStudent->id,
            'relationship' => 'Parent',
            'status' => 'active',
            'requested_by' => $guardian->id,
            'reviewed_by' => $linkedStudent->id,
            'requested_at' => now()->subMinute(),
            'reviewed_at' => now(),
        ]);

        Enrollment::create(['user_id' => $linkedStudent->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);
        [$otherTeacher, $otherBatch] = $this->makeBatch();
        $unlinkedStudent->update(['branch_id' => $otherBatch->branch_id]);
        Enrollment::create(['user_id' => $unlinkedStudent->id, 'course_id' => $otherBatch->course_id, 'batch_id' => $otherBatch->id, 'status' => 'active']);

        LiveClass::create([
            'batch_id' => $otherBatch->id,
            'title' => 'Unlinked student private live class',
            'meeting_url' => 'https://meet.example.com/unlinked-private-meeting',
            'starts_at' => now()->addMinutes(10),
            'ends_at' => now()->addHour(),
            'status' => 'scheduled',
            'created_by' => $otherTeacher->id,
        ]);

        LiveClass::create([
            'batch_id' => $batch->id,
            'title' => 'Linked student live class',
            'meeting_url' => 'https://meet.example.com/guardian-private-meeting',
            'starts_at' => now()->addMinutes(10),
            'ends_at' => now()->addHour(),
            'status' => 'scheduled',
            'created_by' => $teacher->id,
        ]);

        $this->actingAs($guardian)->get(route('live-classes.index'))
            ->assertOk()
            ->assertSee('Linked student live class')
            ->assertDontSee('Unlinked student private live class')
            ->assertDontSee('https://meet.example.com/unlinked-private-meeting')
            ->assertDontSee('https://meet.example.com/guardian-private-meeting');
    }

    public function test_guardian_can_watch_only_published_recordings_for_currently_linked_students(): void
    {
        [$teacher, $batch, $course] = $this->makeBatch();
        $guardian = User::factory()->create(['role' => 'guardian', 'branch_id' => $batch->branch_id]);
        $linkedStudent = User::factory()->create(['role' => 'student', 'branch_id' => $batch->branch_id]);
        $unlinkedStudent = User::factory()->create(['role' => 'student', 'branch_id' => $batch->branch_id]);

        GuardianStudentLink::create([
            'guardian_id' => $guardian->id,
            'student_id' => $linkedStudent->id,
            'relationship' => 'Parent',
            'status' => 'active',
            'requested_by' => $guardian->id,
            'reviewed_by' => $linkedStudent->id,
            'requested_at' => now()->subMinute(),
            'reviewed_at' => now(),
        ]);

        Enrollment::create(['user_id' => $linkedStudent->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'completed', 'completed_at' => now()->subDay()]);
        [$otherTeacher, $otherBatch] = $this->makeBatch();
        $unlinkedStudent->update(['branch_id' => $otherBatch->branch_id]);
        Enrollment::create(['user_id' => $unlinkedStudent->id, 'course_id' => $otherBatch->course_id, 'batch_id' => $otherBatch->id, 'status' => 'completed', 'completed_at' => now()->subDay()]);

        LiveClass::create([
            'batch_id' => $otherBatch->id,
            'title' => 'Unlinked student private recording',
            'meeting_url' => 'https://meet.example.com/unlinked-recording-meeting',
            'recording_url' => 'https://youtu.be/zzzzzzzzzzz',
            'recording_published_at' => now()->subMinute(),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHour(),
            'status' => 'completed',
            'created_by' => $otherTeacher->id,
        ]);

        LiveClass::create([
            'batch_id' => $batch->id,
            'title' => 'Linked student published recording',
            'meeting_url' => 'https://meet.example.com/guardian-recording-meeting',
            'recording_url' => 'https://youtu.be/abcdefghijk',
            'recording_published_at' => now()->subMinute(),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHour(),
            'status' => 'completed',
            'created_by' => $teacher->id,
        ]);

        $this->actingAs($guardian)->get(route('live-classes.index'))
            ->assertOk()
            ->assertSee('Linked student published recording')
            ->assertSee('https://www.youtube-nocookie.com/embed/abcdefghijk', false)
            ->assertDontSee('Unlinked student private recording')
            ->assertDontSee('https://www.youtube-nocookie.com/embed/zzzzzzzzzzz', false)
            ->assertDontSee('https://meet.example.com/unlinked-recording-meeting')
            ->assertDontSee('https://meet.example.com/guardian-recording-meeting');
    }

    public function test_branch_managers_and_accountants_cannot_view_global_live_class_schedule(): void
    {
        $branch = Branch::create(['name' => 'Restricted Staff Branch', 'slug' => 'restricted-staff-branch']);
        foreach (['branch_manager', 'accountant'] as $role) {
            $user = User::factory()->create(['role' => $role, 'branch_id' => $branch->id]);
            $this->actingAs($user)->get(route('live-classes.index'))->assertForbidden();
        }
    }

}
