<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTranscriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_and_print_their_own_transcript_page(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('student.transcript'))
            ->assertOk()
            ->assertSee('Academic Transcript')
            ->assertSee($student->name)
            ->assertSee('Print / Save PDF');
    }

    public function test_transcript_includes_own_course_progress_and_certificates_without_leaking_other_students_records(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);

        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'My English Course']);
        $lesson = $course->lessons()->create([
            'title' => 'Completed lesson',
            'content' => 'Lesson content',
            'duration_minutes' => 20,
            'order' => 1,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        LessonProgress::create([
            'lesson_id' => $lesson->id,
            'user_id' => $student->id,
            'completed_at' => now(),
        ]);
        Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'certificate_number' => 'AEC-TRANSCRIPT-001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $otherCourse = Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'Private Other Course']);
        Enrollment::create([
            'user_id' => $otherStudent->id,
            'course_id' => $otherCourse->id,
            'status' => 'completed',
        ]);
        Certificate::create([
            'user_id' => $otherStudent->id,
            'course_id' => $otherCourse->id,
            'certificate_number' => 'OTHER-STUDENT-SECRET',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('student.transcript'))
            ->assertOk()
            ->assertSee('My English Course')
            ->assertSee('1 / 1')
            ->assertSee('AEC-TRANSCRIPT-001')
            ->assertDontSee('Private Other Course')
            ->assertDontSee('OTHER-STUDENT-SECRET');
    }

    public function test_non_student_cannot_open_student_transcript(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->get(route('student.transcript'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_for_transcript(): void
    {
        $this->get(route('student.transcript'))->assertRedirect(route('login'));
    }
}
