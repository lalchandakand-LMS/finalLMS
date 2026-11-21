<?php

declare(strict_types=1);

namespace Aloms\ReferralIncome;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ReferralIncomeService
{
    private function ownerType(User $user): string
    {
        if ($user->role === 'student' || $user->role === 'teacher') return $user->role;
        throw ValidationException::withMessages(['referral' => 'Only students and teachers can participate in referrals.']);
    }

    public function createOrGetCode(User $owner): string
    {
        $type = $this->ownerType($owner);
        $existing = DB::table('referral_codes')->where('owner_user_id', $owner->id)->first();
        if ($existing) return (string) $existing->code;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = Str::upper(Str::random(10));
            try {
                DB::table('referral_codes')->insert([
                    'owner_user_id' => $owner->id, 'owner_type' => $type, 'code' => $code,
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
                return $code;
            } catch (\Illuminate\Database\QueryException $exception) {
                $existing = DB::table('referral_codes')->where('owner_user_id', $owner->id)->first();
                if ($existing) return (string) $existing->code;
                if ($attempt === 4) throw $exception;
            }
        }
        throw new RuntimeException('Could not create a referral code.');
    }

    public function applyCode(User $buyer, string $rawCode): void
    {
        $code = Str::upper(trim($rawCode));
        DB::transaction(function () use ($buyer, $code): void {
            $lockedBuyer = User::query()->lockForUpdate()->findOrFail($buyer->id);
            $this->ownerType($lockedBuyer);
            $referrer = DB::table('referral_codes')->where('code', $code)->where('is_active', true)->lockForUpdate()->first();
            if (!$referrer) throw ValidationException::withMessages(['referral_code' => 'Referral code is invalid or inactive.']);
            if ((int) $referrer->owner_user_id === (int) $lockedBuyer->id) {
                throw ValidationException::withMessages(['referral_code' => 'You cannot use your own referral code.']);
            }
            if (Payment::query()->where('user_id', $lockedBuyer->id)->where('status', 'approved')->exists()) {
                throw ValidationException::withMessages(['referral_code' => 'Referral codes must be applied before the first approved payment.']);
            }
            if (DB::table('referral_attributions')->where('referred_user_id', $lockedBuyer->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['referral_code' => 'A referral has already been assigned to this account.']);
            }
            DB::table('referral_attributions')->insert([
                'referrer_user_id' => $referrer->owner_user_id,
                'referred_user_id' => $lockedBuyer->id,
                'referral_code_id' => $referrer->id,
                'referrer_type' => $referrer->owner_type,
                'status' => 'active',
                'attributed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit('attribution.created', (string) $lockedBuyer->id, ['referrer_id' => (int) $referrer->owner_user_id]);
        });
    }

    /** Call only after the Payment row has been authoritatively approved by a manual review or verified gateway callback. */
    public function recordApprovedPayment(int $paymentId): ?int
    {
        if (!Schema::hasTable('referral_commissions')) return null;
        return DB::transaction(function () use ($paymentId): ?int {
            $payment = Payment::query()->lockForUpdate()->find($paymentId);
            if (!$payment || $payment->status !== 'approved' || (float) $payment->amount <= 0
                || strtoupper((string) $payment->currency) !== 'BDT'
                || $payment->learn_earn_exam_id) return null;
            $attribution = DB::table('referral_attributions')->where('referred_user_id', $payment->user_id)
                ->where('status', 'active')->lockForUpdate()->first();
            if (!$attribution) return null;
            $rule = DB::table('referral_income_rules')->where('owner_type', $attribution->referrer_type)
                ->where('is_active', true)->lockForUpdate()->first();
            if (!$rule || (float) $rule->commission_percent <= 0) return null;
            $amount = round((float) $payment->amount, 2);
            $commission = round($amount * ((float) $rule->commission_percent / 100), 2);
            if ($commission <= 0) return null;
            $inserted = DB::table('referral_commissions')->insertOrIgnore([
                'attribution_id' => $attribution->id,
                'payment_id' => $payment->id,
                'referrer_user_id' => $attribution->referrer_user_id,
                'referred_user_id' => $payment->user_id,
                'eligible_amount' => $amount,
                'commission_percent' => $rule->commission_percent,
                'commission_amount' => $commission,
                'currency' => 'BDT',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $id = DB::table('referral_commissions')->where('payment_id', $payment->id)->value('id');
            if ($inserted) $this->audit('commission.created', (string) $id, ['payment_id' => $payment->id, 'amount' => $commission]);
            return $id ? (int) $id : null;
        });
    }

    public function requestPayout(User $owner, string $method, string $account): int
    {
        $this->ownerType($owner);
        if (!in_array($method, ['bkash', 'nagad', 'bank'], true) || mb_strlen(trim($account)) < 6) {
            throw ValidationException::withMessages(['payout' => 'Enter a valid bKash, Nagad or bank payout destination.']);
        }
        return DB::transaction(function () use ($owner, $method, $account): int {
            $commissions = DB::table('referral_commissions')->where('referrer_user_id', $owner->id)
                ->where('currency', 'BDT')->where('status', 'approved')->orderBy('id')->lockForUpdate()->get();
            $amount = round((float) $commissions->sum('commission_amount'), 2);
            $rule = DB::table('referral_income_rules')->where('owner_type', $owner->role)->first();
            $minimum = (float) ($rule->minimum_payout ?? 500);
            if ($amount < $minimum) throw ValidationException::withMessages(['payout' => 'Your approved balance has not reached the minimum payout.']);
            $id = DB::table('referral_payout_requests')->insertGetId([
                'user_id' => $owner->id, 'owner_type' => $owner->role, 'amount' => $amount,
                'currency' => 'BDT', 'method' => $method,
                'account_details_encrypted' => Crypt::encryptString(trim($account)),
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('referral_commissions')->whereIn('id', $commissions->pluck('id'))
                ->update(['status' => 'payout_requested', 'payout_request_id' => $id, 'updated_at' => now()]);
            $this->audit('payout.requested', (string) $id, ['amount' => $amount, 'method' => $method]);
            return $id;
        });
    }

    public function reviewCommission(int $id, int $adminId, string $status, ?string $note): void
    {
        if (!in_array($status, ['approved', 'rejected'], true) || ($status === 'rejected' && trim((string) $note) === '')) {
            throw ValidationException::withMessages(['status' => 'Select a valid review status and provide a reason for rejection.']);
        }
        DB::transaction(function () use ($id, $adminId, $status, $note): void {
            $row = DB::table('referral_commissions')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row && $row->status === 'pending', 409);
            DB::table('referral_commissions')->where('id', $id)->update([
                'status' => $status, 'reviewed_by' => $adminId, 'reviewed_at' => now(),
                'admin_note' => $note, 'updated_at' => now(),
            ]);
            $this->audit('commission.reviewed', (string) $id, ['status' => $status, 'reason' => $note], $adminId);
        });
    }

    public function reviewPayout(int $id, int $adminId, string $status, ?string $note): void
    {
        if (!in_array($status, ['approved', 'paid', 'rejected'], true) || ($status === 'paid' && trim((string) $note) === '')) {
            throw ValidationException::withMessages(['status' => 'A payout reference is required when marking a payout paid.']);
        }
        DB::transaction(function () use ($id, $adminId, $status, $note): void {
            $row = DB::table('referral_payout_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row && in_array($row->status, ['pending', 'approved'], true), 409);
            if ($status === 'paid') abort_unless($row->status === 'approved', 409);
            DB::table('referral_payout_requests')->where('id', $id)->update([
                'status' => $status, 'admin_note' => $note, 'reviewed_by' => $adminId,
                'reviewed_at' => now(), 'paid_at' => $status === 'paid' ? now() : null, 'updated_at' => now(),
            ]);
            $commissionStatus = $status === 'paid' ? 'paid' : ($status === 'rejected' ? 'approved' : 'payout_requested');
            DB::table('referral_commissions')->where('payout_request_id', $id)->where('status', 'payout_requested')
                ->update(['status' => $commissionStatus, 'updated_at' => now()]);
            $this->audit('payout.reviewed', (string) $id, ['status' => $status, 'note' => $note], $adminId);
        });
    }

    private function audit(string $event, string $subject, array $metadata, ?int $actorId = null): void
    {
        DB::table('referral_audit_events')->insert([
            'actor_id' => $actorId, 'event' => $event, 'subject_id' => $subject,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
