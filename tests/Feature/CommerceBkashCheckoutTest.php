<?php

namespace Tests\Feature;

use App\Models\CommercePaymentTransaction;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommerceBkashCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user',
            'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key',
            'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);
    }

    public function test_bkash_order_payment_is_verified_and_fulfilled_idempotently(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Commerce Gateway Course',
            'slug' => 'commerce-gateway-course',
            'description' => 'Test course.',
            'price' => 1250,
            'status' => 'published',
        ]);
        $product = Product::create([
            'type' => 'course',
            'course_id' => $course->id,
            'name' => 'Commerce Gateway Course',
            'slug' => 'commerce-gateway-course-product',
            'regular_price' => 1250,
            'currency' => 'BDT',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $admin->id,
        ]);
        Setting::setValue('payment_methods', json_encode([
            'bkash' => ['enabled' => true, 'label' => 'bKash'],
        ]));

        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_ends_with($url, '/tokenized/checkout/token/grant')) {
                return Http::response(['id_token' => 'commerce-test-token'], 200);
            }
            if (str_ends_with($url, '/tokenized/checkout/create')) {
                return Http::response([
                    'statusCode' => '0000',
                    'paymentID' => 'COMMERCE-BKASH-PAY-001',
                    'bkashURL' => 'https://tokenized.sandbox.bka.sh/checkout/pay/COMMERCE-BKASH-PAY-001',
                ], 200);
            }
            if (str_ends_with($url, '/tokenized/checkout/execute')) {
                return Http::response(['transactionStatus' => 'Completed'], 200);
            }
            if (str_ends_with($url, '/tokenized/checkout/payment/status')) {
                $transaction = CommercePaymentTransaction::where('gateway_payment_id', $request['paymentID'])->firstOrFail();
                return Http::response([
                    'transactionStatus' => 'Completed',
                    'paymentID' => $request['paymentID'],
                    'trxID' => 'COMMERCE-BKASH-TRX-001',
                    'amount' => '1250.00',
                    'currency' => 'BDT',
                    'merchantInvoiceNumber' => $transaction->invoice,
                ], 200);
            }
            return Http::response([], 404);
        });

        $this->actingAs($student)->post(route('store.cart.add', $product))->assertRedirect();
        $this->actingAs($student)->post(route('store.checkout'), [
            'payment_method' => 'bkash',
        ])->assertRedirect(route('store.orders'));

        $order = Order::where('user_id', $student->id)->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertStringStartsWith('ONLINE-', $order->payment_reference);

        $this->actingAs($student)->post(route('store.orders.pay.bkash', $order))
            ->assertRedirect('https://tokenized.sandbox.bka.sh/checkout/pay/COMMERCE-BKASH-PAY-001');

        $transaction = CommercePaymentTransaction::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('pending', $transaction->status);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);

        // The browser may return a failure/cancel status even after the provider
        // completed the charge; the server-side payment-status query is authoritative.
        $callback = route('store.payments.bkash.callback', [
            'paymentID' => 'COMMERCE-BKASH-PAY-001',
            'status' => 'failure',
        ]);
        $this->get($callback)->assertRedirect(route('store.orders'));

        $this->assertDatabaseHas('commerce_payment_transactions', [
            'id' => $transaction->id,
            'status' => 'verified',
            'gateway_transaction_id' => 'COMMERCE-BKASH-TRX-001',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed']);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('COMMERCE-BKASH-TRX-001')
            ->assertSee('VERIFIED');

        // Replayed callbacks must not create duplicate enrollments or coupon/download side effects.
        $this->get($callback)->assertRedirect(route('store.orders'));
        $this->assertSame(1, Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->count());
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/tokenized/checkout/payment/status'));
    }

    public function test_student_cannot_initiate_bkash_payment_for_another_users_order(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $order = Order::create([
            'order_number' => 'ALM-OWNER-CHECK-001',
            'user_id' => $owner->id,
            'status' => 'pending',
            'payment_method' => 'bkash',
            'payment_reference' => 'ONLINE-OWNER-CHECK-001',
            'currency' => 'BDT',
            'subtotal' => 100,
            'discount_total' => 0,
            'total' => 100,
        ]);

        $this->actingAs($other)
            ->post(route('store.orders.pay.bkash', $order))
            ->assertNotFound();

        $this->assertDatabaseMissing('commerce_payment_transactions', ['order_id' => $order->id]);
    }

    public function test_bkash_order_callback_amount_mismatch_does_not_fulfill_order(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $order = Order::create([
            'order_number' => 'ALM-AMOUNT-CHECK-001',
            'user_id' => $student->id,
            'status' => 'pending',
            'payment_method' => 'bkash',
            'payment_reference' => 'ONLINE-AMOUNT-CHECK-001',
            'currency' => 'BDT',
            'subtotal' => 100,
            'discount_total' => 0,
            'total' => 100,
        ]);
        $transaction = CommercePaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => 'bkash',
            'invoice' => 'ALM-ORDER-AMOUNT-CHECK',
            'gateway_payment_id' => 'COMMERCE-BKASH-AMOUNT-001',
            'amount' => '100.00',
            'currency' => 'BDT',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'amount-check-token'], 200),
            '*/tokenized/checkout/execute' => Http::response([], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'COMMERCE-BKASH-AMOUNT-001',
                'trxID' => 'COMMERCE-BKASH-AMOUNT-TRX',
                'amount' => '1.00',
                'currency' => 'BDT',
                'merchantInvoiceNumber' => $transaction->invoice,
            ], 200),
        ]);

        $this->get(route('store.payments.bkash.callback', [
            'paymentID' => 'COMMERCE-BKASH-AMOUNT-001',
            'status' => 'success',
        ]))->assertRedirect(route('store.orders'));

        $this->assertDatabaseHas('commerce_payment_transactions', ['id' => $transaction->id, 'status' => 'pending']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }
}
