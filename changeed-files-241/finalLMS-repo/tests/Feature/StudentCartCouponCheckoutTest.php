<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Payment;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentCartCouponCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_preview_coupon_discount_with_server_calculated_prices(): void
    {
        [$student, $course, $token] = $this->fixture();
        Coupon::create([
            'code' => 'WELCOME20', 'name' => 'Welcome discount',
            'discount_type' => 'percentage', 'discount_value' => 20,
            'minimum_subtotal' => 100, 'per_user_limit' => 1, 'is_active' => true,
        ]);

        $this->withToken($token)->postJson('/api/v1/my/cart', ['course_id' => $course->id])->assertCreated();
        $this->withToken($token)->postJson('/api/v1/my/cart/coupon', ['code' => 'welcome20'])
            ->assertOk()
            ->assertJsonPath('data.code', 'WELCOME20')
            ->assertJsonPath('data.subtotal', '1000.00')
            ->assertJsonPath('data.discount', '200.00')
            ->assertJsonPath('data.total', '800.00');
    }

    public function test_coupon_is_reserved_until_payment_approval_and_released_on_rejection(): void
    {
        [$student, $course, $token] = $this->fixture();
        $admin = User::factory()->create(['role' => 'admin']);
        $coupon = Coupon::create([
            'code' => 'SAVE200', 'name' => 'Fixed discount',
            'discount_type' => 'fixed', 'discount_value' => 200,
            'minimum_subtotal' => 0, 'per_user_limit' => 1, 'is_active' => true,
        ]);
        \App\Models\Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => '', 'instructions' => ''],
        ]));

        $this->withToken($token)->postJson('/api/v1/my/cart', ['course_id' => $course->id])->assertCreated();
        $response = $this->withToken($token)->postJson('/api/v1/my/cart/checkout/manual', [
            'payment_method' => 'manual',
            'transaction_reference' => 'CART-COUPON-REF-001',
            'coupon_code' => $coupon->code,
        ])->assertCreated()->assertJsonPath('data.amount', '800.00');

        $paymentId = $response->json('data.payment_id');
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id, 'payment_id' => $paymentId, 'status' => 'reserved',
        ]);

        $this->actingAs($admin)->post(route('admin.payments.reject', $paymentId), ['admin_note' => 'Invalid receipt'])->assertRedirect();
        $this->assertDatabaseHas('coupon_redemptions', ['payment_id' => $paymentId, 'status' => 'released']);
    }

    public function test_multi_course_cart_checkout_creates_one_payment_and_approval_enrolls_all_courses(): void
    {
        [$student, $first, $token, $teacher] = $this->fixture();
        $second = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published', 'price' => 500]);
        $admin = User::factory()->create(['role' => 'admin']);
        \App\Models\Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => '', 'instructions' => ''],
        ]));

        $this->withToken($token)->postJson('/api/v1/my/cart', ['course_id' => $first->id])->assertCreated();
        $this->withToken($token)->postJson('/api/v1/my/cart', ['course_id' => $second->id])->assertCreated();

        $response = $this->withToken($token)->postJson('/api/v1/my/cart/checkout/manual', [
            'payment_method' => 'manual',
            'transaction_reference' => 'CART-MULTI-REF-001',
        ])->assertCreated()->assertJsonPath('data.course_count', 2)->assertJsonPath('data.amount', '1500.00');

        $paymentId = $response->json('data.payment_id');
        $payment = Payment::findOrFail($paymentId);
        $this->assertNotNull($payment->course_combo_id);
        $this->assertDatabaseCount('payment_combo_courses', 2);
        $this->assertDatabaseCount('student_cart_items', 0);

        $this->actingAs($admin)->post(route('admin.payments.approve', $payment))->assertRedirect();
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $first->id, 'status' => 'active']);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $second->id, 'status' => 'active']);
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id, 'title' => 'Coupon Course',
            'status' => 'published', 'price' => 1000,
        ]);
        $plain = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $student->id, 'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Cart checkout test', 'status' => 'approved',
            'phone_verified_at' => now(), 'approved_at' => now(),
        ]);
        ApiToken::create([
            'user_id' => $student->id, 'student_device_id' => $device->id,
            'name' => 'Cart checkout test', 'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return [$student, $course, $plain, $teacher];
    }
}
