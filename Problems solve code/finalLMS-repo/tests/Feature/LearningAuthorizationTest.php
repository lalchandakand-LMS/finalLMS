<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unenrolled_student_only_sees_preview_lessons_and_cannot_complete_course(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'English Foundations',
            'slug' => 'english-foundations',
            'description' => 'Public course description.',
            'price' => 0,
            'status' => 'published',
        ]);
        Lesson::create([
            'course_id' => $course->id,
            'title' => 'Preview lesson',
            'content' => 'Public sample content.',
            'duration_minutes' => 10,
            'order' => 1,
            'is_preview' => true,
        ]);
        Lesson::create([
            'course_id' => $course->id,
            'title' => 'Paid lesson',
            'content' => 'Private paid course content.',
            'duration_minutes' => 20,
            'order' => 2,
            'is_preview' => false,
        ]);

        $this->actingAs($student)
            ->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Public sample content.')
            ->assertDontSee('Private paid course content.');

        $this->post(route('courses.complete', $course))
            ->assertNotFound();

        $this->assertDatabaseMissing('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_teacher_cannot_view_an_unassigned_students_certificate(): void
    {
        $ownerTeacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $ownerTeacher->id,
            'title' => 'Business English',
            'slug' => 'business-english',
            'description' => 'Business English.',
            'price' => 0,
            'status' => 'published',
        ]);
        $certificate = Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'certificate_number' => 'CERT-PRIVATE-001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->actingAs($otherTeacher)
            ->get(route('certificates.show', $certificate))
            ->assertForbidden();
    }

    public function test_student_dashboard_only_shows_their_own_certificates(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Pronunciation',
            'slug' => 'pronunciation',
            'description' => 'Pronunciation practice.',
            'price' => 0,
            'status' => 'published',
        ]);
        Certificate::create([
            'user_id' => $otherStudent->id,
            'course_id' => $course->id,
            'certificate_number' => 'CERT-OTHER-STUDENT',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('CERT-OTHER-STUDENT');
    }

    public function test_student_must_complete_every_lesson_before_receiving_a_certificate(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Grammar Basics',
            'slug' => 'grammar-basics',
            'description' => 'Grammar practice.',
            'price' => 0,
            'status' => 'published',
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Nouns',
            'content' => 'Noun lesson.',
            'duration_minutes' => 10,
            'order' => 1,
            'is_preview' => false,
        ]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);

        $this->actingAs($student)
            ->from(route('courses.show', $course))
            ->post(route('courses.complete', $course))
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('certificates', ['user_id' => $student->id, 'course_id' => $course->id]);

        $this->post(route('courses.lessons.complete', [$course, $lesson]))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('lesson_progress', ['user_id' => $student->id, 'lesson_id' => $lesson->id]);

        $this->post(route('courses.complete', $course))->assertSessionHas('success');
        $this->assertDatabaseHas('certificates', ['user_id' => $student->id, 'course_id' => $course->id]);
    }
}
