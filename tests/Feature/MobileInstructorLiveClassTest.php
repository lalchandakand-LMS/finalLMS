<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileInstructorLiveClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_schedule_list_and_update_owned_live_classes(): void
    {
        [$teacher, $batch] = $this->fixture();
        $token = $this->token($teacher);

        $created = $this->withToken($token)->postJson('/api/v1/instructor/batches/'.$batch->id.'/live-classes', [
            'title' => 'Speaking practice live session',
            'description' => 'Bring your speaking notes.',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'ends_at' => now()->addDays(2)->addHour()->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('live_class.title', 'Speaking practice live session')
            ->assertJsonPath('live_class.status', 'scheduled')
            ->assertJsonPath('live_class.batch_id', $batch->id);

        $liveClassId = $created->json('live_class.id');

        $this->withToken($token)->getJson('/api/v1/instructor/live-classes')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $liveClassId)
            ->assertJsonPath('data.0.course', 'Instructor Live Class Course');

        $this->withToken($token)->patchJson('/api/v1/instructor/live-classes/'.$liveClassId, [
            'status' => 'completed',
            'recording_url' => 'https://youtu.be/abcdefghijk',
            'recording_notes' => 'Recording and homework review.',
        ])->assertOk()
            ->assertJsonPath('live_class.status', 'completed')
            ->assertJsonPath('live_class.recording_url', 'https://youtu.be/abcdefghijk');

        $this->assertDatabaseHas('live_classes', [
            'id' => $liveClassId,
            'status' => 'completed',
            'recording_url' => 'https://youtu.be/abcdefghijk',
            'recording_notes' => 'Recording and homework review.',
        ]);
        $this->assertNotNull(LiveClass::findOrFail($liveClassId)->recording_published_at);
    }

    public function test_instructor_cannot_manage_another_teachers_live_classes(): void
    {
        [$teacher, $batch] = $this->fixture();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $liveClass = LiveClass::create([
            'batch_id' => $batch->id,
            'title' => 'Private live session',
            'description' => null,
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'scheduled',
            'created_by' => $teacher->id,
        ]);

        $otherToken = $this->token($otherTeacher);
        $this->withToken($otherToken)->getJson('/api/v1/instructor/live-classes')->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($otherToken)->patchJson('/api/v1/instructor/live-classes/'.$liveClass->id, [
            'status' => 'cancelled',
        ])->assertNotFound();

        $this->assertDatabaseHas('live_classes', ['id' => $liveClass->id, 'status' => 'scheduled']);
    }

    public function test_live_class_creation_requires_secure_meeting_url_and_valid_schedule(): void
    {
        [$teacher, $batch] = $this->fixture();
        $token = $this->token($teacher);

        $this->withToken($token)->postJson('/api/v1/instructor/batches/'.$batch->id.'/live-classes', [
            'title' => 'Insecure meeting link',
            'meeting_url' => 'http://example.com/meeting',
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'ends_at' => now()->addDays(2)->addHour()->toIso8601String(),
        ])->assertUnprocessable();

        $this->withToken($token)->postJson('/api/v1/instructor/batches/'.$batch->id.'/live-classes', [
            'title' => 'Invalid time range',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('live_classes', 0);
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Instructor Live Class Course',
            'slug' => 'instructor-live-class-course',
            'description' => 'Course for mobile live class tests.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create([
            'name' => 'Instructor Live Class Branch',
            'slug' => 'instructor-live-class-branch',
        ]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Instructor Live Class Batch',
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);

        return [$teacher, $batch];
    }

    private function token(User $user): string
    {
        $plain = Str::random(80);
        $user->apiTokens()->create([
            'name' => 'Instructor live class test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
