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
        preg_match_all('/[^\\x00-\\x7F]/u', $payload, $unicodeCharacters);
        $unicodeCount = count($unicodeCharacters[0]);
        $characterCount = mb_strlen($payload, 'UTF-8');
        $asciiCount = max(0, $characterCount - $unicodeCount);
        // Count non-ASCII code points individually and allow a compact 3:1 ratio only for ASCII.
        $promptTokens = max(1, $unicodeCount + (int) ceil($asciiCount / 3) + (4 * count($messages)));
        $date = today()->toDateString();
        $reservationId = (string) Str::uuid();

        return DB::transaction(function () use ($dailyBudget, $configuredLimit, $promptTokens, $date, $reservationId): ?array {
            DB::table('ai_assistant_token_reservations')
                ->whereIn('status', ['completed', 'released', 'expired'])
                ->where('created_at', '<', now()->subDays(30))
                ->delete();

            $created = DB::table('ai_assistant_daily_budgets')->insertOrIgnore([
                'budget_date' => $date,
                'used_tokens' => 0,
                'reserved_tokens' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ledger = DB::table('ai_assistant_daily_budgets')->where('budget_date', $date)->lockForUpdate()->first();
            if ($created > 0 && $ledger) {
                $legacyUsage = (int) DB::table('ai_assistant_messages')
                    ->where('message_role', 'assistant')
                    ->whereDate('created_at', $date)
                    ->sum('token_count');
                if ($legacyUsage > 0) {
                    DB::table('ai_assistant_daily_budgets')->where('id', $ledger->id)->update([
                        'used_tokens' => $legacyUsage,
                        'updated_at' => now(),
                    ]);
                    $ledger->used_tokens = $legacyUsage;
                }
            }
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
            $snapshot = DB::table('ai_assistant_token_reservations')->where('id', $reservationId)->first();
            if (! $snapshot) return;

            $ledger = DB::table('ai_assistant_daily_budgets')->where('budget_date', $snapshot->budget_date)->lockForUpdate()->first();
            $reservation = DB::table('ai_assistant_token_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            if (! $reservation || $reservation->status !== 'reserved' || ! $ledger) return;

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
            $snapshot = DB::table('ai_assistant_token_reservations')->where('id', $reservationId)->first();
            if (! $snapshot) return;

            $ledger = DB::table('ai_assistant_daily_budgets')->where('budget_date', $snapshot->budget_date)->lockForUpdate()->first();
            $reservation = DB::table('ai_assistant_token_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            if (! $reservation || $reservation->status !== 'reserved') return;

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
