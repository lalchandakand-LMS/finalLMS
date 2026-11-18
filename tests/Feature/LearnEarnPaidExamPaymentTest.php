<?php

namespace Tests\Feature;

use Aloms\LearnEarn\LearnEarnExam;
use Aloms\LearnEarn\LearnEarnExamEntitlement;
use Aloms\LearnEarn\LearnEarnExamAttempt;
use Aloms\LearnEarn\LearnEarnAttemptQuestion;
use Aloms\LearnEarn\LearnEarnExamQuestion;
use Aloms\LearnEarn\LearnEarnExamAnswer;
use Aloms\LearnEarn\LearnEarnScorer;
use Aloms\LearnEarn\StudentLearnEarnController;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LearnEarnPaidExamPaymentTest extends TestCase
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

        // Plugin migrations create tables at runtime. MySQL implicitly commits DDL,
        // so temporarily end RefreshDatabase's transaction and start a fresh one
        // after the package schema is installed.
        $connection = DB::connection();
        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        foreach ([
            '2026_11_17_150000_create_learn_earn_question_tables.php',
            '2026_11_17_160000_create_learn_earn_exam_tables.php',
            '2026_11_17_170000_create_learn_earn_attempt_tables.php',
            '2026_11_17_180000_create_learn_earn_payment_entitlements.php',
        ] as $migrationFile) {
            $migration = require base_path('plugins/aec/learn-earn/1.0.0/migrations/'.$migrationFile);
            $migration->up();
        }

        $connection->beginTransaction();

        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExam.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExamEntitlement.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/StudentLearnEarnController.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExamAttempt.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnAttemptQuestion.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExamQuestion.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExamAnswer.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnScorer.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExamAttempt.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnAttemptQuestion.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExamQuestion.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExamAnswer.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnScorer.php');
    }

    public function test_paid_exam_entitlement_is_granted_only_after_bkash_server_verification(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $exam = $this->paidExam();

        Http::fake(function (ClientRequest $clientRequest) {
            $url = $clientRequest->url();

            if (str_ends_with($url, '/tokenized/checkout/token/grant')) {
                return Http::response(['id_token' => 'verification-token'], 200);
            }

            if (str_ends_with($url, '/tokenized/checkout/create')) {
                return Http::response([
                    'statusCode' => '0000',
                    'paymentID' => 'BKASH-LEARN-EARN-001',
                    'bkashURL' => 'https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-LEARN-EARN-001',
                ], 200);
            }

            if (str_ends_with($url, '/tokenized/checkout/execute')
                || str_ends_with($url, '/tokenized/checkout/payment/status')) {
                $paymentId = (string) $clientRequest['paymentID'];
                $payment = Payment::query()->where('gateway_payment_id', $paymentId)->first();

                return Http::response([
                    'transactionStatus' => 'Completed',
                    'paymentID' => $paymentId,
                    'trxID' => 'BKASH-LEARN-EARN-TRX-001',
                    'amount' => '350.00',
                    'currency' => 'BDT',
                    'merchantInvoiceNumber' => $payment?->transaction_reference,
                ], 200);
            }

            return Http::response(['message' => 'Unexpected bKash request.'], 404);
        });

        $request = Request::create('/student/learn-earn/exams/'.$exam->slug.'/checkout/bkash', 'POST');
        $request->setUserResolver(fn () => $student);

        $response = app(StudentLearnEarnController::class)->checkoutBkash(
            $request,
            $exam,
            app(PaymentGatewayManager::class),
        );

        $this->assertSame('https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-LEARN-EARN-001', $response->getTargetUrl());

        $payment = Payment::query()->where('gateway_payment_id', 'BKASH-LEARN-EARN-001')->firstOrFail();



        $this->assertSame('pending', $payment->status);
        $this->assertDatabaseMissing('learn_earn_exam_entitlements', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
        ]);


        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-LEARN-EARN-001',
            'status' => 'success',
        ]))->assertRedirect('/student/learn-earn/exams');

        $this->assertSame('approved', $payment->fresh()->status, 'Verified bKash callback should approve the payment.');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'approved',
            'gateway_transaction_id' => 'BKASH-LEARN-EARN-TRX-001',
        ], null);
        $this->assertDatabaseHas('learn_earn_exam_entitlements', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'payment_id' => $payment->id,
            'status' => 'active',
        ]);
        $this->assertSame(1, LearnEarnExamEntitlement::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->count());

        Http::assertSent(fn (ClientRequest $clientRequest) =>
            str_ends_with($clientRequest->url(), '/tokenized/checkout/payment/status')
            && $clientRequest['paymentID'] === 'BKASH-LEARN-EARN-001'
        );

        Http::preventStrayRequests();
        $this->get(route('payments.bkash.callback', ['paymentID' => 'BKASH-LEARN-EARN-001', 'status' => 'success']))
            ->assertRedirect('/student/learn-earn/exams');
        $this->assertDatabaseCount('learn_earn_exam_entitlements', 1);
    }

    public function test_paid_exam_callback_with_wrong_amount_or_invoice_never_grants_access(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $exam = $this->paidExam();
        $payment = Payment::query()->create([
            'user_id' => $student->id,
            'course_id' => null,
            'order_id' => null,
            'learn_earn_exam_id' => $exam->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '350.00',
            'currency' => 'BDT',
            'transaction_reference' => 'LEARN-EARN-TEST-INVOICE-2',
            'gateway_payment_id' => 'BKASH-LEARN-EARN-002',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'verification-token'], 200),
            '*/tokenized/checkout/execute' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-LEARN-EARN-002',
                'trxID' => 'BKASH-LEARN-EARN-TRX-002',
                'amount' => '350.00',
                'currency' => 'BDT',
                'merchantInvoiceNumber' => 'WRONG-INVOICE',
            ], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-LEARN-EARN-002',
                'trxID' => 'BKASH-LEARN-EARN-TRX-002',
                'amount' => '1.00',
                'currency' => 'BDT',
                'merchantInvoiceNumber' => 'WRONG-INVOICE',
            ], 200),
        ]);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-LEARN-EARN-002',
            'status' => 'success',
        ]))->assertRedirect('/student/learn-earn/exams');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('learn_earn_exam_entitlements', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_admin_payment_review_lists_exam_payments_but_cannot_manually_grant_or_reject_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $exam = $this->paidExam();
        $payment = Payment::query()->create([
            'user_id' => $student->id,
            'course_id' => null,
            'order_id' => null,
            'learn_earn_exam_id' => $exam->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '350.00',
            'currency' => 'BDT',
            'transaction_reference' => 'LEARN-EARN-ADMIN-REVIEW',
            'gateway_payment_id' => 'BKASH-LEARN-EARN-ADMIN-REVIEW',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Paid Grammar Exam');

        $this->post(route('admin.payments.approve', $payment))->assertRedirect();
        $this->post(route('admin.payments.reject', $payment), ['admin_note' => 'manual'])->assertRedirect();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('learn_earn_exam_entitlements', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_paid_exam_callback_does_not_grant_access_if_exam_is_unpublished(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $exam = $this->paidExam();
        DB::table('learn_earn_exams')->where('id', $exam->id)->update(['status' => 'draft']);
        $payment = Payment::query()->create([
            'user_id' => $student->id,
            'course_id' => null,
            'order_id' => null,
            'learn_earn_exam_id' => $exam->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '350.00',
            'currency' => 'BDT',
            'transaction_reference' => 'LEARN-EARN-UNPUBLISHED-INVOICE',
            'gateway_payment_id' => 'BKASH-LEARN-EARN-UNPUBLISHED',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'verification-token'], 200),
            '*/tokenized/checkout/execute' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-LEARN-EARN-UNPUBLISHED',
                'trxID' => 'BKASH-LEARN-EARN-UNPUBLISHED-TRX',
                'amount' => '350.00',
                'currency' => 'BDT',
                'merchantInvoiceNumber' => $payment->transaction_reference,
            ], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-LEARN-EARN-UNPUBLISHED',
                'trxID' => 'BKASH-LEARN-EARN-UNPUBLISHED-TRX',
                'amount' => '350.00',
                'currency' => 'BDT',
                'merchantInvoiceNumber' => $payment->transaction_reference,
            ], 200),
        ]);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-LEARN-EARN-UNPUBLISHED',
            'status' => 'success',
        ]))->assertRedirect('/student/learn-earn/exams');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'rejected']);
        $this->assertDatabaseMissing('learn_earn_exam_entitlements', [
            'exam_id' => $exam->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_plugin_uninstall_guard_preserves_all_schema_when_exam_content_exists(): void
    {
        $exam = $this->paidExam();
        $migration = require base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_180000_create_learn_earn_payment_entitlements.php');

        try {
            $migration->down();
            $this->fail('The newest plugin migration must block partial rollback when exam content exists.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('preserve the complete plugin schema', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('payments', 'learn_earn_exam_id'));
        $this->assertTrue(Schema::hasTable('learn_earn_exam_entitlements'));
        $this->assertDatabaseHas('learn_earn_exams', ['id' => $exam->id]);
    }

    public function test_expired_attempt_page_redirects_and_finalizes_without_a_view_return_type_error(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $exam = $this->paidExam();
        $attempt = LearnEarnExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now()->subHour(),
            'ends_at' => now()->subMinute(),
            'score' => 0,
            'max_score' => 0,
        ]);

        if (! Route::has('student.learn-earn.attempts.result')) {
            Route::get('/__test/learn-earn/attempts/{attempt}', static fn () => response()->noContent())
                ->name('student.learn-earn.attempts.result');
        }

        $request = Request::create('/student/learn-earn/attempts/'.$attempt->id, 'GET');
        $request->setUserResolver(fn () => $student);
        $response = app(StudentLearnEarnController::class)->attempt($request, $attempt);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertSame(route('student.learn-earn.attempts.result', $attempt), $response->getTargetUrl());
        $this->assertDatabaseHas('learn_earn_exam_attempts', [
            'id' => $attempt->id,
            'status' => 'expired',
        ]);
    }

    private function paidExam(): LearnEarnExam
    {
        $id = DB::table('learn_earn_exams')->insertGetId([
            'title' => 'Paid Grammar Exam',
            'slug' => 'paid-grammar-exam-'.uniqid(),
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'duration_minutes' => 30,
            'pass_marks' => 1,
            'max_attempts' => 1,
            'randomize_questions' => true,
            'access_type' => 'paid',
            'price' => '350.00',
            'currency' => 'BDT',
            'status' => 'published',
            'show_results' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return LearnEarnExam::query()->findOrFail($id);
    }
}
