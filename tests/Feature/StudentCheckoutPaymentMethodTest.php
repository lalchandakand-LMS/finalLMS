<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Course;
use App\Models\Setting;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentCheckoutPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_options_separate_bkash_from_manual_payment_methods(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'price' => 1200,
            'status' => 'published',
        ]);
        Setting::setValue('payment_methods', json_encode([
            'bkash' => ['enabled' => true, 'label' => 'bKash'],
            'rocket' => ['enabled' => true, 'label' => 'Rocket'],
        ]));
        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'test-user',
            'services.bkash.password' => 'test-password',
            'services.bkash.app_key' => 'test-app-key',
            'services.bkash.app_secret' => 'test-app-secret',
        ]);

        $this->withToken($this->studentToken($student))
            ->getJson('/api/v1/checkout/courses/'.$course->slug)
            ->assertOk()
            ->assertJsonPath('data.online_bkash_available', true)
            ->assertJsonCount(1, 'data.payment_methods')
            ->assertJsonPath('data.payment_methods.0.key', 'rocket');
    }

    public function test_bkash_cannot_be_submitted_through_manual_payment_endpoint(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'price' => 1200,
            'status' => 'published',
        ]);
        Setting::setValue('payment_methods', json_encode([
            'bkash' => ['enabled' => true],
            'rocket' => ['enabled' => true],
        ]));

        $this->withToken($this->studentToken($student))
            ->postJson('/api/v1/checkout/courses/'.$course->slug.'/manual', [
                'payment_method' => 'bkash',
                'transaction_reference' => 'BKASH-MANUAL-001',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_bkash_endpoint_requires_the_admin_payment_method_toggle_even_if_credentials_exist(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'price' => 1200,
            'status' => 'published',
        ]);
        Setting::setValue('payment_methods', json_encode([
            'bkash' => ['enabled' => false],
        ]));
        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'test-user',
            'services.bkash.password' => 'test-password',
            'services.bkash.app_key' => 'test-app-key',
            'services.bkash.app_secret' => 'test-app-secret',
        ]);

        $this->withToken($this->studentToken($student))
            ->postJson('/api/v1/checkout/courses/'.$course->slug.'/bkash')
            ->assertStatus(503);

        $this->assertDatabaseCount('payments', 0);
    }

    private function studentToken(User $student): string
    {
        $token = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Checkout payment test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        $student->apiTokens()->create([
            'student_device_id' => $device->id,
            'name' => 'Checkout payment test',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        return $token;
    }
}
