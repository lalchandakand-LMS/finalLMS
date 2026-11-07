<?php

namespace Tests\Feature;

use App\Models\Course;
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

        foreach (['bkash', 'nagad', 'rocket', 'bank', 'manual'] as $method) {
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
        $this->assertFalse((bool) Setting::paymentMethods()['nagad']['enabled']);

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
}
