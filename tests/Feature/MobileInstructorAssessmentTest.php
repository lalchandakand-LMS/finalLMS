<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileInstructorAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_view_assessments_and_paginated_student_results(): void
    {
        [$teacher, $batch, $student] = $this->fixture();
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Midterm assessment',
            'instructions' => 'Choose the correct answers.',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 6,
            'status' => 'published',
        ]);
        ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now()->subMinutes(2),
            'score' => 8,
            'status' => 'submitted',
        ]);

        $token = $this->token($teacher);
        $this->withToken($token)->getJson('/api/v1/instructor/exams')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $exam->id)
            ->assertJsonPath('data.0.submitted_attempts_count', 1);

        $this->withToken($token)->getJson('/api/v1/instructor/exams/'.$exam->id.'/results')
            ->assertOk()
            ->assertJsonPath('exam.title', 'Midterm assessment')
            ->assertJsonPath('summary.submitted_attempts', 1)
            ->assertJsonPath('summary.passed_attempts', 1)
            ->assertJsonPath('summary.pass_rate', 100)
            ->assertJsonPath('data.0.student.id', $student->id)
            ->assertJsonPath('data.0.passed', true);
    }

    public function test_instructor_cannot_view_another_teachers_assessment_results_and_students_are_denied(): void
    {
        [$teacher, $batch] = $this->fixture();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $otherBatch = Batch::create([
            'branch_id' => $batch->branch_id,
            'course_id' => $batch->course_id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Other teacher batch',
            'capacity' => 10,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        $exam = Exam::create([
            'batch_id' => $otherBatch->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Private assessment',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 5,
            'status' => 'published',
        ]);

        $this->withToken($this->token($teacher))
            ->getJson('/api/v1/instructor/exams/'.$exam->id.'/results')
            ->assertNotFound();
        $this->withToken($this->token(User::factory()->create(['role' => 'student'])))
            ->getJson('/api/v1/instructor/exams')
            ->assertForbidden();
        $this->withToken($this->token(User::factory()->create(['role' => 'admin'])))
            ->getJson('/api/v1/instructor/exams/'.$exam->id.'/results')
            ->assertForbidden();
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Assessment Mobile Course',
            'slug' => 'assessment-mobile-course',
            'description' => 'Assessment API tests.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Assessment Branch', 'slug' => 'assessment-branch']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Assessment Batch',
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
        $device = null;
        if (in_array($user->role, ['student', 'teacher'], true)) {
            $device = StudentDevice::create([
                'user_id' => $user->id,
                'device_hash' => hash('sha256', Str::random(64)),
                'device_name' => 'Instructor assessment test device',
                'status' => 'approved',
                'phone_verified_at' => now(),
                'approved_at' => now(),
            ]);
        }

        $user->apiTokens()->create([
            'student_device_id' => $device?->id,
            'name' => 'Instructor assessment test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
