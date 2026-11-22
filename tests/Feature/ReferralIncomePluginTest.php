<?php

declare(strict_types=1);

namespace Tests\Feature;

use Aloms\ReferralIncome\ReferralIncomeService;
use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ReferralIncomePluginTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('referral_audit_events');
        Schema::dropIfExists('referral_payout_requests');
        Schema::dropIfExists('referral_commissions');
        Schema::dropIfExists('referral_attributions');
        Schema::dropIfExists('referral_codes');
        Schema::dropIfExists('referral_income_rules');
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('plugins/aec/referral-income/1.0.0/src/ReferralIncomeService.php');
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('referral_audit_events');
        Schema::dropIfExists('referral_payout_requests');
        Schema::dropIfExists('referral_commissions');
        Schema::dropIfExists('referral_attributions');
        Schema::dropIfExists('referral_codes');
        Schema::dropIfExists('referral_income_rules');
        Schema::enableForeignKeyConstraints();
        $migration = require base_path('plugins/aec/referral-income/1.0.0/migrations/2026_11_21_040000_create_referral_income_tables.php');
        $migration->up();
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
        $this->assertDatabaseCount('referral_commissions', 1);
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

    public function test_payout_requires_approved_minimum_balance_and_keeps_destination_encrypted(): void
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
    }
}
