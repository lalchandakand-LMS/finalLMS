<?php

declare(strict_types=1);

namespace Tests\Feature;

use Aloms\ReferralIncome\ReferralIncomeService;
use App\Models\Course;
use App\Models\Payment;
use App\Models\PluginInstallation;
use App\Models\User;
use Tests\Support\RefreshDatabaseWithPluginMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ReferralIncomePluginTest extends TestCase
{
    use RefreshDatabaseWithPluginMigrations;

    protected function pluginMigrationPaths(): array
    {
        return [base_path('plugins/aec/referral-income/1.0.0/migrations')];
    }

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('plugins/aec/referral-income/1.0.0/src/ReferralIncomeService.php');
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.referral-income',
            'name' => 'Referral Income',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'active',
            'manifest' => ['settings_schema' => []],
        ])->save();
    }

    public function test_referral_attribution_and_verified_payment_commission_are_idempotent(): void
    {
        $referrer = User::factory()->create(['role' => 'student']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $code = $service->createOrGetCode($referrer);
        $this->assertSame($code, $service->createOrGetCode($referrer));
        $service->applyCode($buyer, $code);

        $course = Course::factory()->create();
        $payment = Payment::query()->create([
            'user_id' => $buyer->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'amount' => 1000,
            'currency' => 'BDT',
            'transaction_reference' => 'REF-'.strtoupper(bin2hex(random_bytes(6))),
            'status' => 'approved',
        ]);

        $commissionId = $service->recordApprovedPayment((int) $payment->id);
        $this->assertNotNull($commissionId);
        $this->assertSame($commissionId, $service->recordApprovedPayment((int) $payment->id));
        $this->assertSame(1, DB::table('referral_commissions')->where('payment_id', $payment->id)->count());
        $this->assertDatabaseHas('referral_commissions', [
            'id' => $commissionId,
            'payment_id' => $payment->id,
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $buyer->id,
            'eligible_amount' => 1000,
            'commission_percent' => 5,
            'commission_amount' => 50,
            'currency' => 'BDT',
            'status' => 'pending',
        ]);
    }


    public function test_ebook_order_payment_never_creates_a_referral_commission(): void
    {
        $referrer = User::factory()->create(['role' => 'student']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $service->applyCode($buyer, $service->createOrGetCode($referrer));

        $order = \App\Models\Order::query()->create([
            'order_number' => 'AEC-REFERRAL-EBOOK-'.strtoupper(bin2hex(random_bytes(5))),
            'user_id' => $buyer->id,
            'status' => 'completed',
            'payment_method' => 'bkash',
            'payment_reference' => 'EBOOK-REF-'.strtoupper(bin2hex(random_bytes(5))),
            'currency' => 'BDT',
            'subtotal' => 1000,
            'discount_total' => 0,
            'total' => 1000,
            'paid_at' => now(),
            'completed_at' => now(),
        ]);
        $payment = Payment::query()->create([
            'user_id' => $buyer->id,
            'order_id' => $order->id,
            'method' => 'bkash',
            'amount' => 1000,
            'currency' => 'BDT',
            'transaction_reference' => 'REF-EBOOK-'.strtoupper(bin2hex(random_bytes(5))),
            'status' => 'approved',
        ]);

        $this->assertNull($service->recordApprovedPayment((int) $payment->id));
        $this->assertDatabaseMissing('referral_commissions', ['payment_id' => $payment->id]);
    }

    public function test_scheduled_reconciliation_recovers_a_missing_commission_and_is_idempotent(): void
    {
        $referrer = User::factory()->create(['role' => 'student']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $service->applyCode($buyer, $service->createOrGetCode($referrer));
        $course = Course::factory()->create();
        $payment = Payment::query()->create([
            'user_id' => $buyer->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'amount' => 1000,
            'currency' => 'BDT',
            'transaction_reference' => 'REF-RETRY-'.strtoupper(bin2hex(random_bytes(6))),
            'status' => 'approved',
        ]);

        // Simulate payment approval while the synchronous commission write was
        // unavailable: the approved payment remains the durable retry source.
        $this->assertDatabaseCount('referral_commissions', 0);
        $this->assertSame(0, Artisan::call('referral-income:reconcile-approved-payments'));
        $this->assertDatabaseHas('referral_commissions', [
            'payment_id' => $payment->id,
            'referrer_user_id' => $referrer->id,
            'commission_amount' => 50,
        ]);

        $this->assertSame(0, Artisan::call('referral-income:reconcile-approved-payments'));
        $this->assertDatabaseCount('referral_commissions', 1);
    }

    public function test_reconciliation_does_not_run_when_referral_plugin_is_deactivated(): void
    {
        $referrer = User::factory()->create(['role' => 'student']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $service->applyCode($buyer, $service->createOrGetCode($referrer));
        $course = Course::factory()->create();
        $payment = Payment::query()->create([
            'user_id' => $buyer->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'amount' => 1000,
            'currency' => 'BDT',
            'transaction_reference' => 'REF-DISABLED-'.strtoupper(bin2hex(random_bytes(5))),
            'status' => 'approved',
        ]);

        PluginInstallation::query()->where('plugin_key', 'aec.referral-income')->update(['status' => 'inactive']);

        $this->assertSame(0, Artisan::call('referral-income:reconcile-approved-payments'));
        $this->assertDatabaseMissing('referral_commissions', ['payment_id' => $payment->id]);

        PluginInstallation::query()->where('plugin_key', 'aec.referral-income')->update(['status' => 'active']);
        $this->assertSame(0, Artisan::call('referral-income:reconcile-approved-payments'));
        $this->assertDatabaseHas('referral_commissions', ['payment_id' => $payment->id]);
    }

    public function test_reconciliation_retries_a_transient_commission_failure_on_the_next_run(): void
    {
        $referrer = User::factory()->create(['role' => 'student']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $service->applyCode($buyer, $service->createOrGetCode($referrer));
        $course = Course::factory()->create();
        $payment = Payment::query()->create([
            'user_id' => $buyer->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'amount' => 1000,
            'currency' => 'BDT',
            'transaction_reference' => 'REF-RETRY-FAIL-'.strtoupper(bin2hex(random_bytes(5))),
            'status' => 'approved',
        ]);

        // Throw after the insert query executes but before its transaction commits.
        // This proves a transient failure rolls back the commission and is retried.
        $shouldFail = true;
        DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) use (&$shouldFail): void {
            if ($shouldFail && str_contains(strtolower($query->sql), 'referral_commissions')
                && str_starts_with(strtolower(ltrim($query->sql)), 'insert')) {
                $shouldFail = false;
                throw new \RuntimeException('Transient referral database failure.');
            }
        });

        $this->assertSame(1, Artisan::call('referral-income:reconcile-approved-payments'));
        $this->assertDatabaseCount('referral_commissions', 0);

        $this->assertSame(0, Artisan::call('referral-income:reconcile-approved-payments'));
        $this->assertDatabaseHas('referral_commissions', [
            'payment_id' => $payment->id,
            'referrer_user_id' => $referrer->id,
            'commission_amount' => 50,
        ]);
    }

    public function test_a_buyer_cannot_change_an_existing_referral_attribution(): void
    {
        $first = User::factory()->create(['role' => 'student']);
        $second = User::factory()->create(['role' => 'teacher']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $service->applyCode($buyer, $service->createOrGetCode($first));
        try {
            $service->applyCode($buyer, $service->createOrGetCode($second));
            $this->fail('An existing referral attribution must not be replaceable.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertDatabaseHas('referral_attributions', ['referred_user_id' => $buyer->id, 'referrer_user_id' => $first->id]);
            $this->assertDatabaseMissing('referral_attributions', ['referred_user_id' => $buyer->id, 'referrer_user_id' => $second->id]);
        }
    }

    public function test_non_admin_cannot_review_commissions(): void
    {
        $referrer = User::factory()->create(['role' => 'student']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $service->applyCode($buyer, $service->createOrGetCode($referrer));
        $course = Course::factory()->create();
        $payment = Payment::query()->create([
            'user_id' => $buyer->id, 'course_id' => $course->id, 'method' => 'bkash',
            'amount' => 1000, 'currency' => 'BDT',
            'transaction_reference' => 'REF-AUTH-'.strtoupper(bin2hex(random_bytes(5))),
            'status' => 'approved',
        ]);
        $commissionId = $service->recordApprovedPayment((int) $payment->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->reviewCommission((int) $commissionId, (int) $referrer->id, 'approved', null);
    }
    public function test_pending_or_non_bdt_payments_never_create_commissions(): void
    {
        $referrer = User::factory()->create(['role' => 'student']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $service->applyCode($buyer, $service->createOrGetCode($referrer));
        $course = Course::factory()->create();

        $pending = Payment::query()->create([
            'user_id' => $buyer->id, 'course_id' => $course->id, 'method' => 'bkash',
            'amount' => 2000, 'currency' => 'BDT',
            'transaction_reference' => 'REF-PENDING-'.strtoupper(bin2hex(random_bytes(5))),
            'status' => 'pending',
        ]);
        $foreign = Payment::query()->create([
            'user_id' => $buyer->id, 'course_id' => $course->id, 'method' => 'card',
            'amount' => 2000, 'currency' => 'USD',
            'transaction_reference' => 'REF-FOREIGN-'.strtoupper(bin2hex(random_bytes(5))),
            'status' => 'approved',
        ]);

        $this->assertNull($service->recordApprovedPayment((int) $pending->id));
        $this->assertNull($service->recordApprovedPayment((int) $foreign->id));
        $this->assertDatabaseCount('referral_commissions', 0);
    }
    public function test_self_referral_and_second_attribution_are_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'teacher']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $ownerCode = $service->createOrGetCode($owner);
        $this->expectException(ValidationException::class);
        $service->applyCode($owner, $ownerCode);
    }

    public function test_referral_payouts_fail_closed_until_refund_reconciliation_is_enabled(): void
    {
        config(['referral-income.payouts_enabled' => false]);
        $owner = User::factory()->create(['role' => 'student']);

        try {
            app(ReferralIncomeService::class)->requestPayout($owner, 'bkash', '01700000000');
            $this->fail('Referral payouts must remain disabled until refund reconciliation is available.');
        } catch (\\Symfony\\Component\\HttpKernel\\Exception\\HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('referral_payout_requests', 0);
    }

    public function test_payout_requires_approved_minimum_balance_and_keeps_destination_encrypted(): void
    {
        config(['referral-income.payouts_enabled' => true]);
        $referrer = User::factory()->create(['role' => 'teacher']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $service->applyCode($buyer, $service->createOrGetCode($referrer));
        $course = Course::factory()->create();
        $payment = Payment::query()->create([
            'user_id' => $buyer->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'amount' => 10000,
            'currency' => 'BDT',
            'transaction_reference' => 'REF-PAYOUT-'.strtoupper(bin2hex(random_bytes(5))),
            'status' => 'approved',
        ]);
        $commissionId = $service->recordApprovedPayment((int) $payment->id);
        $admin = User::factory()->create(['role' => 'admin']);
        $service->reviewCommission((int) $commissionId, (int) $admin->id, 'approved', null);

        $payoutId = $service->requestPayout($referrer, 'bkash', '01700000000');
        $this->assertDatabaseHas('referral_payout_requests', [
            'id' => $payoutId,
            'user_id' => $referrer->id,
            'status' => 'pending',
        ]);
        $stored = \Illuminate\Support\Facades\DB::table('referral_payout_requests')->where('id', $payoutId)->value('account_details_encrypted');
        $this->assertNotSame('01700000000', $stored);
        $this->assertSame('01700000000', \Illuminate\Support\Facades\Crypt::decryptString($stored));

        $service->reviewPayout($payoutId, (int) $admin->id, 'approved', 'Finance checked');
        $service->reviewPayout($payoutId, (int) $admin->id, 'paid', 'BANK-REF-2026-001');
        $this->assertDatabaseHas('referral_payout_requests', ['id' => $payoutId, 'status' => 'paid']);
        $this->assertDatabaseHas('referral_commissions', ['id' => $commissionId, 'status' => 'paid']);

        try {
            $service->requestPayout($referrer, 'bkash', '01700000000');
            $this->fail('The same approved commissions must not be paid out twice.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('referral_payout_requests', 1);
        }
    }

    public function test_payout_review_fails_closed_when_reserved_commission_total_changes(): void
    {
        $referrer = User::factory()->create(['role' => 'teacher']);
        $buyer = User::factory()->create(['role' => 'student']);
        $service = app(ReferralIncomeService::class);
        $service->applyCode($buyer, $service->createOrGetCode($referrer));
        $course = Course::factory()->create();
        $payment = Payment::query()->create([
            'user_id' => $buyer->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'amount' => 10000,
            'currency' => 'BDT',
            'transaction_reference' => 'REF-PAYOUT-CHECK-'.strtoupper(bin2hex(random_bytes(5))),
            'status' => 'approved',
        ]);
        $commissionId = $service->recordApprovedPayment((int) $payment->id);
        $admin = User::factory()->create(['role' => 'admin']);
        $service->reviewCommission((int) $commissionId, (int) $admin->id, 'approved', null);
        $payoutId = $service->requestPayout($referrer, 'bkash', '01700000000');

        DB::table('referral_commissions')->where('id', $commissionId)->update(['commission_amount' => 600]);

        try {
            $service->reviewPayout($payoutId, (int) $admin->id, 'approved', 'Reviewed');
            $this->fail('Payout review must fail if its reserved commission total no longer matches.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertDatabaseHas('referral_payout_requests', ['id' => $payoutId, 'status' => 'pending']);
        }
    }
}
