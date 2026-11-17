<?php

namespace Aloms\UniversalAiAssistant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AssistantTokenBudget
{
    /**
     * Atomically reserve prompt + completion capacity before contacting a provider.
     * Reservations expire after two minutes so crashed requests cannot lock a budget forever.
     *
     * @return array{id:string,max_tokens:int,reserved_tokens:int}|null
     */
    public static function reserve(int $dailyBudget, int $configuredLimit, array $messages): ?array
    {
        $payload = json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $promptTokens = max(1, (int) ceil(mb_strlen($payload) / 3));
        $date = today()->toDateString();
        $reservationId = (string) Str::uuid();

        return DB::transaction(function () use ($dailyBudget, $configuredLimit, $promptTokens, $date, $reservationId): ?array {
            DB::table('ai_assistant_daily_budgets')->insertOrIgnore([
                'budget_date' => $date,
                'used_tokens' => 0,
                'reserved_tokens' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ledger = DB::table('ai_assistant_daily_budgets')->where('budget_date', $date)->lockForUpdate()->first();
            if (! $ledger) return null;

            $expired = DB::table('ai_assistant_token_reservations')
                ->where('budget_date', $date)
                ->where('status', 'reserved')
                ->where('expires_at', '<=', now())
                ->lockForUpdate()
                ->get();

            $expiredTokens = (int) $expired->sum('reserved_tokens');
            if ($expired->isNotEmpty()) {
                DB::table('ai_assistant_token_reservations')
                    ->whereIn('id', $expired->pluck('id'))
                    ->update(['status' => 'expired', 'updated_at' => now()]);
                DB::table('ai_assistant_daily_budgets')->where('id', $ledger->id)->update([
                    'reserved_tokens' => max(0, (int) $ledger->reserved_tokens - $expiredTokens),
                    'updated_at' => now(),
                ]);
                $ledger->reserved_tokens = max(0, (int) $ledger->reserved_tokens - $expiredTokens);
            }

            $available = max(0, $dailyBudget - (int) $ledger->used_tokens - (int) $ledger->reserved_tokens);
            $completionTokens = min(max(0, $configuredLimit), $available - $promptTokens);
            if ($completionTokens < 1) return null;

            $reservedTokens = $promptTokens + $completionTokens;
            DB::table('ai_assistant_token_reservations')->insert([
                'id' => $reservationId,
                'budget_date' => $date,
                'reserved_tokens' => $reservedTokens,
                'status' => 'reserved',
                'expires_at' => now()->addMinutes(2),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ai_assistant_daily_budgets')->where('id', $ledger->id)->update([
                'reserved_tokens' => (int) $ledger->reserved_tokens + $reservedTokens,
                'updated_at' => now(),
            ]);

            return ['id' => $reservationId, 'max_tokens' => $completionTokens, 'reserved_tokens' => $reservedTokens];
        }, 3);
    }

    public static function settle(string $reservationId, int $actualTokens): void
    {
        DB::transaction(function () use ($reservationId, $actualTokens): void {
            $reservation = DB::table('ai_assistant_token_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            if (! $reservation || $reservation->status !== 'reserved') return;

            $ledger = DB::table('ai_assistant_daily_budgets')->where('budget_date', $reservation->budget_date)->lockForUpdate()->first();
            if (! $ledger) return;

            DB::table('ai_assistant_daily_budgets')->where('id', $ledger->id)->update([
                'reserved_tokens' => max(0, (int) $ledger->reserved_tokens - (int) $reservation->reserved_tokens),
                'used_tokens' => (int) $ledger->used_tokens + max(0, $actualTokens),
                'updated_at' => now(),
            ]);
            DB::table('ai_assistant_token_reservations')->where('id', $reservationId)->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public static function release(string $reservationId): void
    {
        DB::transaction(function () use ($reservationId): void {
            $reservation = DB::table('ai_assistant_token_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            if (! $reservation || $reservation->status !== 'reserved') return;

            $ledger = DB::table('ai_assistant_daily_budgets')->where('budget_date', $reservation->budget_date)->lockForUpdate()->first();
            if ($ledger) {
                DB::table('ai_assistant_daily_budgets')->where('id', $ledger->id)->update([
                    'reserved_tokens' => max(0, (int) $ledger->reserved_tokens - (int) $reservation->reserved_tokens),
                    'updated_at' => now(),
                ]);
            }
            DB::table('ai_assistant_token_reservations')->where('id', $reservationId)->update([
                'status' => 'released',
                'updated_at' => now(),
            ]);
        }, 3);
    }
}
