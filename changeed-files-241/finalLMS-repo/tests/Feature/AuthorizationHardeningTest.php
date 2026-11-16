<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_published_courses_but_not_drafts(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $published = $this->course($teacher, 'Public Course', 'published');
        $draft = $this->course($teacher, 'Private Draft', 'draft');

        $this->get(route('courses.index'))
            ->assertOk()
            ->assertSee('Public Course')
            ->assertDontSee('Private Draft');

        $this->get(route('courses.show', $published))->assertOk();
        $this->get(route('courses.show', $draft))->assertNotFound();
    }

    public function test_student_catalog_hides_unpublished_courses_and_unenrolled_lesson_content(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $draft = $this->course($teacher, 'Private Draft', 'draft');
        $published = $this->course($teacher, 'Published English', 'published');
        $published->lessons()->create([
            'title' => 'Restricted lesson',
            'content' => 'Secret lesson material.',
            'duration_minutes' => 20,
            'order' => 1,
        ]);

        $this->actingAs($student)
            ->get(route('courses.index'))
            ->assertOk()
            ->assertDontSee('Private Draft');

        $this->get(route('courses.show', $draft))->assertNotFound();
        $this->get(route('courses.show', $published))
            ->assertOk()
            ->assertDontSee('Secret lesson material.');

        Enrollment::create(['user_id' => $student->id, 'course_id' => $published->id, 'status' => 'active']);

        $this->get(route('courses.show', $published))
            ->assertOk()
            ->assertSee('Secret lesson material.');
    }

    public function test_teacher_cannot_complete_another_teachers_course_for_a_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $owner = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course($owner, 'Owner Course', 'published');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);

        $this->actingAs($teacher)
            ->post(route('courses.complete', $course), ['student_id' => $student->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_teacher_cannot_view_an_unassigned_students_certificate(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $courseOwner = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course($courseOwner, 'English Course', 'published');
        $certificate = Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'certificate_number' => 'CERT-TEST-1',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->get(route('certificates.show', $certificate))
            ->assertForbidden();
    }

    public function test_student_must_complete_each_lesson_before_course_certificate(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course($teacher, 'Grammar Basics', 'published');
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
            ->post(route('courses.complete', $course))
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('certificates', ['user_id' => $student->id, 'course_id' => $course->id]);

        $this->post(route('courses.lessons.complete', [$course, $lesson]))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('lesson_progress', ['user_id' => $student->id, 'lesson_id' => $lesson->id]);

        $this->post(route('courses.complete', $course))->assertSessionHas('success');
        $this->assertDatabaseHas('certificates', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_student_cannot_complete_a_draft_course(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $draft = $this->course($teacher, 'Draft Course', 'draft');

        $this->actingAs($student)
            ->post(route('courses.enroll', $draft))
            ->assertNotFound();

        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $draft->id]);
    }

    private function course(User $teacher, string $title, string $status): Course
    {
        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'description' => 'Course description.',
            'price' => 0,
            'status' => $status,
        ]);
    }
}
