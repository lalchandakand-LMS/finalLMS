<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstructorAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_aggregates_only_the_authenticated_instructors_academic_records(): void
    {
        [$teacher, $batch, $student] = $this->fixture();
        [$otherTeacher, $otherBatch, $otherStudent] = $this->fixture('Other');

        Enrollment::create(['user_id' => $student->id, 'course_id' => $batch->course_id, 'batch_id' => $batch->id, 'status' => 'active']);
        Enrollment::create(['user_id' => $otherStudent->id, 'course_id' => $otherBatch->course_id, 'batch_id' => $otherBatch->id, 'status' => 'active']);

        Attendance::create(['batch_id' => $batch->id, 'student_id' => $student->id, 'marked_by' => $teacher->id, 'attendance_date' => now()->toDateString(), 'status' => 'present']);
        Attendance::create(['batch_id' => $batch->id, 'student_id' => $student->id, 'marked_by' => $teacher->id, 'attendance_date' => now()->subDay()->toDateString(), 'status' => 'absent']);
        Attendance::create(['batch_id' => $otherBatch->id, 'student_id' => $otherStudent->id, 'marked_by' => $otherTeacher->id, 'attendance_date' => now()->toDateString(), 'status' => 'present']);

        $assignment = $batch->assignments()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Vocabulary assignment',
            'instructions' => 'Write five words.',
            'due_at' => now()->addDay(),
            'max_marks' => 10,
            'status' => 'published',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'Five words',
            'submitted_at' => now(),
            'score' => 8,
            'status' => 'graded',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => User::factory()->create(['role' => 'student'])->id,
            'answer' => 'Another answer',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $batch->assignments()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Draft assignment',
            'instructions' => 'Not published.',
            'due_at' => now()->addDays(2),
            'max_marks' => 5,
            'status' => 'draft',
        ]);

        $batch->exams()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Draft assessment',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'duration_minutes' => 20,
            'pass_marks' => 3,
            'status' => 'draft',
        ]);

        $exam = $batch->exams()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Unit test',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'duration_minutes' => 30,
            'pass_marks' => 5,
            'status' => 'published',
        ]);
        ExamAttempt::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'started_at' => now()->subHour(), 'submitted_at' => now(), 'score' => 8, 'status' => 'submitted']);
        ExamAttempt::create(['exam_id' => $exam->id, 'student_id' => User::factory()->create(['role' => 'student'])->id, 'started_at' => now()->subHour(), 'submitted_at' => now(), 'score' => 2, 'status' => 'submitted']);
        ExamAttempt::create(['exam_id' => $exam->id, 'student_id' => User::factory()->create(['role' => 'student'])->id, 'started_at' => now()->subHour(), 'submitted_at' => now(), 'score' => null, 'status' => 'submitted']);

        // Even inconsistent legacy rows must not leak another teacher's batch data.
        $foreignBatchAssignment = $otherBatch->assignments()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Foreign batch assignment',
            'instructions' => 'Must not be counted.',
            'due_at' => now()->addDay(),
            'max_marks' => 10,
            'status' => 'published',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $foreignBatchAssignment->id,
            'student_id' => $otherStudent->id,
            'answer' => 'Foreign batch submission',
            'submitted_at' => now(),
            'score' => 10,
            'status' => 'graded',
        ]);

        $foreignBatchExam = $otherBatch->exams()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Foreign batch assessment',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'duration_minutes' => 30,
            'pass_marks' => 5,
            'status' => 'published',
        ]);
        ExamAttempt::create([
            'exam_id' => $foreignBatchExam->id,
            'student_id' => $otherStudent->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'score' => 10,
            'status' => 'submitted',
        ]);

        $response = $this->withToken($this->token($teacher))->getJson('/api/v1/instructor/analytics/summary');

        $response->assertOk()
            ->assertJsonPath('data.batches_count', 1)
            ->assertJsonPath('data.active_students_count', 1)
            ->assertJsonPath('data.attendance.total_records', 2)
            ->assertJsonPath('data.attendance.present', 1)
            ->assertJsonPath('data.attendance.absent', 1)
            ->assertJsonPath('data.attendance.attendance_rate', 50)
            ->assertJsonPath('data.assignments.published', 1)
            ->assertJsonPath('data.assignments.submissions', 2)
            ->assertJsonPath('data.assignments.graded', 1)
            ->assertJsonPath('data.assessments.published', 1)
            ->assertJsonPath('data.assessments.submitted_attempts', 3)
            ->assertJsonPath('data.assessments.graded_attempts', 2)
            ->assertJsonPath('data.assessments.passed_attempts', 1)
            ->assertJsonPath('data.assessments.pass_rate', 50)
            ->assertJsonPath('data.assessments.average_score', 5);
    }

    public function test_summary_is_forbidden_to_non_instructor_api_accounts(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->withToken($this->token($student))
            ->getJson('/api/v1/instructor/analytics/summary')
            ->assertForbidden();
    }

    private function fixture(string $suffix = ''): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Analytics Course '.$suffix,
            'slug' => 'analytics-course-'.Str::lower(Str::random(8)),
            'description' => 'Analytics API fixture.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Analytics Branch '.$suffix, 'slug' => 'analytics-branch-'.Str::lower(Str::random(8))]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Analytics Batch '.$suffix,
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);

        return [$teacher, $batch, $student];
    }

    private function token(User $user): string
    {
        $plain = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $user->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Instructor analytics test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);

        $user->apiTokens()->create([
            'student_device_id' => $device->id,
            'name' => 'Instructor analytics test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
