<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseContentPost;
use App\Models\CourseCurriculumItem;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\DownloadPermission;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BkashPaymentFlowTest extends TestCase
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

    public function test_student_is_enrolled_only_after_bkash_server_side_verification(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::sequence()
                ->push(['id_token' => 'init-token'])
                ->push(['id_token' => 'verify-token']),
            '*/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'paymentID' => 'BKASH-PAY-001',
                'bkashURL' => 'https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-PAY-001',
            ], 200),
            '*/tokenized/checkout/execute' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-001',
                'trxID' => 'BKASH-TRX-001',
                'amount' => '1500.00',
            ], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-001',
                'trxID' => 'BKASH-TRX-001',
                'amount' => '1500.00',
            ], 200),
        ]);

        $this->actingAs($student)
            ->post(route('courses.payments.bkash', $course))
            ->assertRedirect('https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-PAY-001');

        $payment = Payment::query()->where('course_id', $course->id)->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-PAY-001',
            'status' => 'success',
        ]))->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'approved',
            'gateway_transaction_id' => 'BKASH-TRX-001',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/payment/status')
            && $request['paymentID'] === 'BKASH-PAY-001'
        );
    }

    public function test_bkash_checkout_applies_category_targeted_flash_sale(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();
        $admin = User::factory()->create(['role' => 'admin']);
        $category = CourseCategory::create([
            'name' => 'Grammar',
            'slug' => 'bkash-grammar-category',
            'is_visible' => true,
        ]);
        $post = CourseContentPost::create([
            'author_id' => $admin->id,
            'title' => 'Grammar curriculum',
            'slug' => 'bkash-grammar-curriculum',
            'long_description' => 'Published grammar curriculum.',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $post->categories()->attach($category->id);
        CourseCurriculumItem::create([
            'course_id' => $course->id,
            'course_content_post_id' => $post->id,
            'position' => 1,
        ]);

        $sale = FlashSale::create([
            'title' => 'Grammar category offer',
            'badge' => 'SAVE 25%',
            'description' => 'Category-targeted bKash discount.',
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'status' => 'active',
            'per_student_limit' => 1,
            'priority' => 10,
            'created_by' => $admin->id,
        ]);
        $sale->categories()->attach($category->id);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'category-sale-token'], 200),
            '*/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'paymentID' => 'BKASH-CATEGORY-SALE-001',
                'bkashURL' => 'https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-CATEGORY-SALE-001',
            ], 200),
        ]);

        $this->actingAs($student)
            ->post(route('courses.payments.bkash', $course))
            ->assertRedirect('https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-CATEGORY-SALE-001');

        $payment = Payment::query()->where('gateway_payment_id', 'BKASH-CATEGORY-SALE-001')->firstOrFail();
        $this->assertSame('1125.00', $payment->amount);
        $this->assertSame($sale->id, $payment->flash_sale_id);
        $this->assertSame('pending', $payment->status);
    }

    public function test_callback_with_amount_mismatch_never_activates_enrollment(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();

        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '1500.00',
            'currency' => 'BDT',
            'transaction_reference' => 'BKASH-LOCAL-REF-2',
            'gateway_payment_id' => 'BKASH-PAY-002',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'verify-token'], 200),
            '*/tokenized/checkout/execute' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-002',
                'trxID' => 'BKASH-TRX-002',
                'amount' => '1500.00',
            ], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-002',
                'trxID' => 'BKASH-TRX-002',
                'amount' => '1.00',
            ], 200),
        ]);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-PAY-002',
            'status' => 'success',
        ]))->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_admin_cannot_manually_approve_or_reject_gateway_verified_bkash_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();
        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '1500.00',
            'currency' => 'BDT',
            'transaction_reference' => 'BKASH-MANUAL-BLOCK-1',
            'gateway_payment_id' => 'BKASH-PAY-MANUAL-BLOCK',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect();

        $this->post(route('admin.payments.reject', $payment), ['admin_note' => 'Manual rejection attempt'])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }


    public function test_callback_retry_after_approval_is_idempotent_and_does_not_call_gateway_again(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();
        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '1500.00',
            'currency' => 'BDT',
            'transaction_reference' => 'BKASH-RETRY-REF',
            'gateway_payment_id' => 'BKASH-PAY-RETRY',
            'gateway_transaction_id' => 'BKASH-TRX-RETRY',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        Http::preventStrayRequests();

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-PAY-RETRY',
            'status' => 'success',
        ]))->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'approved']);
        $this->assertDatabaseCount('enrollments', 1);
        Http::assertNothingSent();
    }

    public function test_status_query_can_confirm_payment_when_execute_request_fails(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();
        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '1500.00',
            'currency' => 'BDT',
            'transaction_reference' => 'BKASH-EXEC-RETRY-REF',
            'gateway_payment_id' => 'BKASH-PAY-EXEC-RETRY',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'verify-token'], 200),
            '*/tokenized/checkout/execute' => Http::response(['message' => 'Already executed'], 409),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-EXEC-RETRY',
                'trxID' => 'BKASH-TRX-EXEC-RETRY',
                'amount' => '1500.00',
                'currency' => 'BDT',
                'merchantInvoiceNumber' => 'BKASH-EXEC-RETRY-REF',
            ], 200),
        ]);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-PAY-EXEC-RETRY',
            'status' => 'success',
        ]))->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'approved',
            'gateway_transaction_id' => 'BKASH-TRX-EXEC-RETRY',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/payment/status')
            && $request['paymentID'] === 'BKASH-PAY-EXEC-RETRY'
        );
    }


    public function test_browser_failure_callback_cannot_reject_a_payment_confirmed_completed_by_bkash(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();
        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '1500.00',
            'currency' => 'BDT',
            'transaction_reference' => 'BKASH-LOCAL-REF-FAIL-CALLBACK',
            'gateway_payment_id' => 'BKASH-PAY-FAIL-CALLBACK',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'verify-token'], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-FAIL-CALLBACK',
                'trxID' => 'BKASH-TRX-FAIL-CALLBACK',
                'amount' => '1500.00',
            ], 200),
        ]);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-PAY-FAIL-CALLBACK',
            'status' => 'failure',
        ]))->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'approved',
            'gateway_transaction_id' => 'BKASH-TRX-FAIL-CALLBACK',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/payment/status')
            && $request['paymentID'] === 'BKASH-PAY-FAIL-CALLBACK'
        );
        Http::assertNotSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/execute')
        );
    }

    public function test_unconfirmed_failure_callback_leaves_payment_pending_for_authoritative_recheck(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();
        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '1500.00',
            'currency' => 'BDT',
            'transaction_reference' => 'BKASH-LOCAL-REF-UNCONFIRMED',
            'gateway_payment_id' => 'BKASH-PAY-UNCONFIRMED',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'verify-token'], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Initiated',
                'paymentID' => 'BKASH-PAY-UNCONFIRMED',
                'amount' => '1500.00',
            ], 200),
        ]);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-PAY-UNCONFIRMED',
            'status' => 'failure',
        ]))->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_student_can_buy_private_ebook_with_bkash_and_download_after_server_verification(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/ebooks/bkash-guide.pdf', 'private ebook bytes');
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->publishedEbook();

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::sequence()
                ->push(['id_token' => 'ebook-init-token'])
                ->push(['id_token' => 'ebook-verify-token']),
            '*/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'paymentID' => 'BKASH-EBOOK-PAY-001',
                'bkashURL' => 'https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-EBOOK-PAY-001',
            ], 200),
            '*/tokenized/checkout/execute' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-EBOOK-PAY-001',
                'trxID' => 'BKASH-EBOOK-TRX-001',
                'amount' => '350.00',
                'currency' => 'BDT',
            ], 200),
            '*/tokenized/checkout/payment/status' => function (Request $request) {
                $payment = Payment::where('gateway_payment_id', $request['paymentID'])->firstOrFail();

                return Http::response([
                    'transactionStatus' => 'Completed',
                    'paymentID' => 'BKASH-EBOOK-PAY-001',
                    'trxID' => 'BKASH-EBOOK-TRX-001',
                    'amount' => '350.00',
                    'currency' => 'BDT',
                    'merchantInvoiceNumber' => $payment->transaction_reference,
                ], 200);
            },
        ]);

        $this->actingAs($student)
            ->post(route('ebooks.checkout.bkash', $product->slug))
            ->assertRedirect('https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-EBOOK-PAY-001');

        $order = Order::where('user_id', $student->id)->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $this->assertNull($payment->course_id);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $payment->status);
        $this->assertDatabaseCount('download_permissions', 0);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-EBOOK-PAY-001',
            'status' => 'success',
        ]))->assertRedirect(route('ebooks.orders'));

        $order->refresh();
        $payment->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('approved', $payment->status);
        $this->assertSame('BKASH-EBOOK-TRX-001', $payment->gateway_transaction_id);
        $permission = DownloadPermission::where('user_id', $student->id)->firstOrFail();
        $this->actingAs($student)->get(route('ebooks.download', $permission))->assertOk();

        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/payment/status')
            && $request['paymentID'] === 'BKASH-EBOOK-PAY-001'
        );
    }

    public function test_ebook_bkash_amount_mismatch_never_grants_download_access(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/ebooks/bkash-guide.pdf', 'private ebook bytes');
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->publishedEbook();

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::sequence()
                ->push(['id_token' => 'ebook-init-token'])
                ->push(['id_token' => 'ebook-verify-token']),
            '*/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'paymentID' => 'BKASH-EBOOK-PAY-002',
                'bkashURL' => 'https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-EBOOK-PAY-002',
            ], 200),
            '*/tokenized/checkout/execute' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-EBOOK-PAY-002',
                'trxID' => 'BKASH-EBOOK-TRX-002',
                'amount' => '350.00',
            ], 200),
            '*/tokenized/checkout/payment/status' => function (Request $request) {
                $payment = Payment::where('gateway_payment_id', $request['paymentID'])->firstOrFail();

                return Http::response([
                    'transactionStatus' => 'Completed',
                    'paymentID' => 'BKASH-EBOOK-PAY-002',
                    'trxID' => 'BKASH-EBOOK-TRX-002',
                    'amount' => '1.00',
                    'currency' => 'BDT',
                    'merchantInvoiceNumber' => $payment->transaction_reference,
                ], 200);
            },
        ]);

        $this->actingAs($student)->post(route('ebooks.checkout.bkash', $product->slug))->assertRedirect();
        $order = Order::where('user_id', $student->id)->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-EBOOK-PAY-002',
            'status' => 'success',
        ]))->assertRedirect(route('ebooks.orders'));

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('download_permissions', 0);
    }

    public function test_admin_cannot_manually_approve_or_reject_bkash_ebook_orders(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/ebooks/bkash-guide.pdf', 'private ebook bytes');
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $product = $this->publishedEbook();
        $order = Order::create([
            'order_number' => 'AEC-BKASH-ADMIN-001',
            'user_id' => $student->id,
            'status' => 'pending',
            'payment_method' => 'bkash',
            'payment_reference' => 'BKASH-EBOOK-ADMIN-001',
            'currency' => 'BDT',
            'subtotal' => 350,
            'discount_total' => 0,
            'total' => 350,
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_type' => 'ebook',
            'product_name' => $product->name,
            'quantity' => 1,
            'regular_price' => 350,
            'line_total' => 350,
        ]);
        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => null,
            'order_id' => $order->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => 350,
            'currency' => 'BDT',
            'transaction_reference' => $order->payment_reference,
            'gateway_payment_id' => 'BKASH-EBOOK-ADMIN-PAY',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.ebooks.orders.index'))
            ->assertOk()
            ->assertSee('Waiting for authoritative bKash server-side verification.')
            ->assertDontSee('Verify & grant download');

        $this->post(route('admin.ebooks.orders.approve', $order))->assertRedirect();
        $this->post(route('admin.ebooks.orders.reject', $order))->assertRedirect();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('download_permissions', 0);
    }

    private function publishedEbook(): Product
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return Product::create([
            'type' => 'ebook',
            'name' => 'bKash Gateway Guide',
            'slug' => 'bkash-gateway-guide',
            'regular_price' => 350,
            'currency' => 'BDT',
            'is_downloadable' => true,
            'download_path' => 'private/ebooks/bkash-guide.pdf',
            'download_limit' => 3,
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'created_by' => $admin->id,
        ]);
    }

    private function paidCourse(): Course
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'bKash Test Course',
            'slug' => 'bkash-test-course-'.$teacher->id,
            'description' => 'Gateway test course.',
            'price' => 1500,
            'status' => 'published',
        ]);
    }
}
