<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCombo;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseComboTest extends TestCase
{
    use RefreshDatabase;


    public function test_admin_can_create_a_published_combo_from_multiple_published_courses(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $first = Course::factory()->create(['teacher_id' => $teacher->id]);
        $second = Course::factory()->create(['teacher_id' => $teacher->id]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.course-combos.store'), [
                'title' => 'Speaking and Grammar Bundle',
                'description' => 'Two courses in one package.',
                'price' => 1800,
                'status' => 'published',
                'course_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('admin.course-combos.index'))
            ->assertSessionHas('success');

        $combo = CourseCombo::query()->where('slug', 'speaking-and-grammar-bundle')->firstOrFail();
        $this->assertSame(2, $combo->courses()->count());
        $this->assertSame('published', $combo->status);
    }

    public function test_public_combo_checkout_records_a_course_snapshot_and_approval_enrolls_that_snapshot(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $courses = collect([
            Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'Spoken English']),
            Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'Grammar Mastery']),
            Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'Writing Skills']),
        ]);
        $combo = CourseCombo::create([
            'title' => 'Complete English Combo',
            'slug' => 'complete-english-combo',
            'description' => 'Three coordinated courses.',
            'price' => 2500,
            'status' => 'published',
        ]);
        $combo->courses()->attach([
            $courses[0]->id => ['position' => 1],
            $courses[1]->id => ['position' => 2],
        ]);
        Setting::setValue('payment_methods', json_encode([
            'bkash' => ['enabled' => true, 'label' => 'bKash', 'account' => '01700000000', 'instructions' => 'Send money'],
        ]));

        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $this->actingAs($student)
            ->post(route('course-combos.enroll', $combo), [
                'payment_method' => 'bkash',
                'sender_account' => '01711111111',
                'transaction_reference' => 'COMBO-REF-1001',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment = Payment::query()->where('course_combo_id', $combo->id)->firstOrFail();
        $this->assertSame($courses[0]->id, $payment->course_id);
        $this->assertSame('pending', $payment->status);
        $this->assertDatabaseCount('payment_combo_courses', 2);
        $this->assertDatabaseCount('enrollments', 0);

        // Editing the combo after checkout must not silently change what the student paid for.
        $combo->courses()->sync([$courses[0]->id, $courses[2]->id]);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $courses[0]->id, 'status' => 'active']);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $courses[1]->id, 'status' => 'active']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $courses[2]->id]);
    }

    public function test_free_combo_enrolls_student_in_every_included_course_without_payment_gateway(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $first = Course::factory()->create(['teacher_id' => $teacher->id, 'price' => 1000]);
        $second = Course::factory()->create(['teacher_id' => $teacher->id, 'price' => 1200]);
        $combo = CourseCombo::create([
            'title' => 'Free Starter Combo',
            'slug' => 'free-starter-combo',
            'price' => 0,
            'status' => 'published',
        ]);
        $combo->courses()->attach([$first->id, $second->id]);
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)
            ->post(route('course-combos.enroll', $combo))
            ->assertRedirect(route('course-combos.show', $combo))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $first->id, 'status' => 'active']);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $second->id, 'status' => 'active']);
    }


    public function test_course_used_in_a_combo_cannot_be_deleted_until_removed_from_the_combo(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $first = Course::factory()->create(['teacher_id' => $teacher->id]);
        $second = Course::factory()->create(['teacher_id' => $teacher->id]);
        $combo = CourseCombo::create([
            'title' => 'Protected Combo',
            'slug' => 'protected-combo',
            'price' => 100,
            'status' => 'draft',
        ]);
        $combo->courses()->attach([$first->id, $second->id]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->delete(route('admin.courses.destroy', $first))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('courses', ['id' => $first->id]);
    }

    public function test_draft_combo_is_not_visible_on_public_pages(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $first = Course::factory()->create(['teacher_id' => $teacher->id]);
        $second = Course::factory()->create(['teacher_id' => $teacher->id]);
        $combo = CourseCombo::create([
            'title' => 'Unpublished Combo',
            'slug' => 'unpublished-combo',
            'price' => 100,
            'status' => 'draft',
        ]);
        $combo->courses()->attach([$first->id, $second->id]);

        $this->get(route('course-combos.index'))->assertOk()->assertDontSee('Unpublished Combo');
        $this->get(route('course-combos.show', $combo))->assertNotFound();
    }
}
