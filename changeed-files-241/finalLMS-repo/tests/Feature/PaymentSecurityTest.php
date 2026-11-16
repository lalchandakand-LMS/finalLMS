<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_reference_cannot_be_reused_for_another_course_payment(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $firstCourse = $this->createPaidCourse($teacher, 'First course', 'first-course');
        $secondCourse = $this->createPaidCourse($teacher, 'Second course', 'second-course');
        Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => 'Account', 'instructions' => 'Pay'],
        ]));

        $this->actingAs($student)
            ->from(route('courses.show', $firstCourse))
            ->post(route('courses.enroll', $firstCourse), [
                'payment_method' => 'manual',
                'transaction_reference' => 'UNIQUE-TXN-001',
            ])
            ->assertRedirect(route('courses.show', $firstCourse));

        $this->from(route('courses.show', $secondCourse))
            ->post(route('courses.enroll', $secondCourse), [
                'payment_method' => 'manual',
                'transaction_reference' => 'UNIQUE-TXN-001',
            ])
            ->assertSessionHasErrors('transaction_reference');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_unpublished_course_rejects_student_enrollment_requests(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Draft course',
            'slug' => 'draft-course',
            'description' => 'Not public.',
            'price' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($student)
            ->post(route('courses.enroll', $course))
            ->assertNotFound();

        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    private function createPaidCourse(User $teacher, string $title, string $slug): Course
    {
        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => $title,
            'slug' => $slug,
            'description' => $title,
            'price' => 100,
            'status' => 'published',
        ]);
    }
}
