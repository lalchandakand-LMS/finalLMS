<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCombo;
use App\Models\Payment;
use App\Models\PaymentComboCourse;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_payment_methods_dynamically(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $paymentMethods = [];

        foreach (['bkash', 'rocket', 'bank', 'manual'] as $method) {
            $paymentMethods[$method] = [
                'enabled' => $method === 'bkash' ? '1' : '0',
                'label' => ucfirst($method),
                'account' => '01700000000',
                'instructions' => 'Include the course name with your payment.',
            ];
        }

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), ['payment_methods' => $paymentMethods])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame('1', Setting::paymentMethods()['bkash']['enabled']);
        $this->assertSame('01700000000', Setting::paymentMethods()['bkash']['account']);
        $this->assertFalse((bool) Setting::paymentMethods()['rocket']['enabled']);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Bangladesh payments')
            ->assertSee('01700000000');

        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'English Foundations',
            'slug' => 'english-foundations',
            'description' => 'Build practical English skills.',
            'price' => 1500,
            'status' => 'published',
        ]);

        $this->actingAs($student)
            ->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('BDT 1,500.00')
            ->assertSee('01700000000')
            ->assertSee('Submit payment for review');
    }

    public function test_paid_course_enrollment_waits_for_admin_payment_approval(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'English Foundations',
            'slug' => 'english-foundations',
            'description' => 'Build practical English skills.',
            'price' => 1500,
            'status' => 'published',
        ]);

        Setting::setValue('payment_methods', json_encode([
            'bkash' => ['enabled' => true, 'label' => 'bKash', 'account' => '01700000000', 'instructions' => 'Send money'],
        ]));

        $this->actingAs($student)
            ->from(route('courses.show', $course))
            ->post(route('courses.enroll', $course), [
                'payment_method' => 'bkash',
                'sender_account' => '01800000000',
                'transaction_reference' => 'TXN-12345',
            ])
            ->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'status' => 'pending',
            'amount' => '1500.00',
        ]);
        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $paymentId = \Illuminate\Support\Facades\DB::table('payments')
            ->where('user_id', $student->id)
            ->value('id');

        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $paymentId))
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('English Foundations')
            ->assertSee('Approved');
    }

    public function test_student_cannot_submit_payment_through_a_disabled_method(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'English Foundations',
            'slug' => 'english-foundations',
            'description' => 'Build practical English skills.',
            'price' => 1500,
            'status' => 'published',
        ]);

        Setting::setValue('payment_methods', json_encode([
            'bkash' => ['enabled' => false, 'label' => 'bKash', 'account' => '01700000000', 'instructions' => 'Send money'],
            'rocket' => ['enabled' => true, 'label' => 'Rocket', 'account' => '01800000000', 'instructions' => 'Send money'],
        ]));

        $this->actingAs($student)
            ->from(route('courses.show', $course))
            ->post(route('courses.enroll', $course), [
                'payment_method' => 'bkash',
                'transaction_reference' => 'TXN-DISABLED',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_direct_manual_checkout_is_blocked_when_course_is_reserved_by_a_pending_combo_payment(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $first = Course::create([
            'teacher_id' => $teacher->id, 'title' => 'Combo First Course',
            'slug' => 'combo-first-course', 'description' => 'First course.',
            'price' => 1000, 'status' => 'published',
        ]);
        $second = Course::create([
            'teacher_id' => $teacher->id, 'title' => 'Combo Second Course',
            'slug' => 'combo-second-course', 'description' => 'Second course.',
            'price' => 1200, 'status' => 'published',
        ]);
        $combo = CourseCombo::create([
            'title' => 'Pending Course Pair', 'slug' => 'pending-course-pair',
            'description' => 'Two course bundle.', 'price' => 1800, 'status' => 'published',
        ]);
        $combo->courses()->attach([$first->id, $second->id]);
        $payment = Payment::create([
            'user_id' => $student->id, 'course_id' => $first->id,
            'course_combo_id' => $combo->id, 'method' => 'manual',
            'amount' => '1800.00', 'currency' => 'BDT',
            'transaction_reference' => 'PENDING-COMBO-001', 'status' => 'pending',
        ]);
        PaymentComboCourse::create([
            'payment_id' => $payment->id, 'course_id' => $first->id,
            'course_title' => $first->title, 'position' => 1,
        ]);
        PaymentComboCourse::create([
            'payment_id' => $payment->id, 'course_id' => $second->id,
            'course_title' => $second->title, 'position' => 2,
        ]);
        Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => '012345', 'instructions' => 'Send payment'],
        ]));

        $this->actingAs($student)
            ->from(route('courses.show', $second))
            ->post(route('courses.enroll', $second), [
                'payment_method' => 'manual',
                'transaction_reference' => 'DUPLICATE-DIRECT-001',
            ])
            ->assertRedirect(route('courses.show', $second))
            ->assertSessionHas('info', 'Your payment is already waiting for review.');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseMissing('payments', ['transaction_reference' => 'DUPLICATE-DIRECT-001']);
    }

    public function test_combo_checkout_is_blocked_when_any_included_course_has_a_pending_direct_payment(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $first = Course::create([
            'teacher_id' => $teacher->id, 'title' => 'Direct Pending Course',
            'slug' => 'direct-pending-course', 'description' => 'First course.',
            'price' => 1000, 'status' => 'published',
        ]);
        $second = Course::create([
            'teacher_id' => $teacher->id, 'title' => 'Combo Companion Course',
            'slug' => 'combo-companion-course', 'description' => 'Second course.',
            'price' => 1200, 'status' => 'published',
        ]);
        $combo = CourseCombo::create([
            'title' => 'Direct Pending Pair', 'slug' => 'direct-pending-pair',
            'description' => 'Two course bundle.', 'price' => 1800, 'status' => 'published',
        ]);
        $combo->courses()->attach([$first->id, $second->id]);
        Payment::create([
            'user_id' => $student->id, 'course_id' => $first->id,
            'method' => 'manual', 'amount' => '1000.00', 'currency' => 'BDT',
            'transaction_reference' => 'PENDING-DIRECT-001', 'status' => 'pending',
        ]);
        Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => '012345', 'instructions' => 'Send payment'],
        ]));

        $this->actingAs($student)
            ->from(route('course-combos.show', $combo))
            ->post(route('course-combos.enroll', $combo), [
                'payment_method' => 'manual',
                'transaction_reference' => 'DUPLICATE-COMBO-001',
            ])
            ->assertRedirect()
            ->assertSessionHas('info', 'Your combo payment is already waiting for review.');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseMissing('payments', ['transaction_reference' => 'DUPLICATE-COMBO-001']);
    }

}
