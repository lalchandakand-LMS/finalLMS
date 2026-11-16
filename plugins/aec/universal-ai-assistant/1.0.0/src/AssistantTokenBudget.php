<?php

namespace Aloms\UniversalAiAssistant;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AssistantTokenBudget
{
    public static function remaining(int $dailyBudget, int $usedTokens): int
    {
        return max(0, max(0, $dailyBudget) - max(0, $usedTokens));
    }

    public static function estimatePromptTokens(array $messages): int
    {
        $payload = json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        // Deliberately conservative estimate; provider tokenizers may differ.
        return max(1, (int) ceil(mb_strlen($payload, 'UTF-8') / 3));
    }

    public static function completionLimit(int $configuredLimit, int $remainingTokens, int $estimatedPromptTokens): int
    {
        return max(0, min(
            max(0, $configuredLimit),
            max(0, $remainingTokens) - max(0, $estimatedPromptTokens)
        ));
    }

    /**
     * Reserve the estimated prompt and maximum completion usage while holding
     * a lock for the institution's local calendar day.
     *
     * @return array{id:int, completion_limit:int}|null
     */
    public static function reserve(
        int $dailyBudget,
        int $estimatedPromptTokens,
        int $configuredCompletionLimit,
        int $userId,
        int $conversationId
    ): ?array {
        $date = today()->toDateString();

        return DB::transaction(function () use (
            $date, $dailyBudget, $estimatedPromptTokens, $configuredCompletionLimit, $userId, $conversationId
        ): ?array {
            DB::table('ai_assistant_budget_days')->insertOrIgnore([
                'budget_date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ai_assistant_budget_days')
                ->where('budget_date', $date)
                ->lockForUpdate()
                ->first();

            $usedTokens = (int) DB::table('ai_assistant_messages')
                ->where('message_role', 'assistant')
                ->whereDate('created_at', $date)
                ->sum('token_count');

            $pendingTokens = (int) DB::table('ai_assistant_token_reservations')
                ->where('budget_date', $date)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->sum(DB::raw('prompt_tokens + completion_limit'));

            $remaining = self::remaining($dailyBudget, $usedTokens + $pendingTokens);
            $completionLimit = self::completionLimit(
                $configuredCompletionLimit,
                $remaining,
                $estimatedPromptTokens
            );

            if ($completionLimit < 1) {
                return null;
            }

            $id = DB::table('ai_assistant_token_reservations')->insertGetId([
                'budget_date' => $date,
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'prompt_tokens' => max(1, $estimatedPromptTokens),
                'completion_limit' => $completionLimit,
                'status' => 'pending',
                'actual_tokens' => null,
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['id' => (int) $id, 'completion_limit' => $completionLimit];
        }, 3);
    }

    /**
     * Persist the assistant response and consume its reservation atomically.
     */
    public static function settle(int $reservationId, int $actualTokens, callable $persistResponse): void
    {
        DB::transaction(function () use ($reservationId, $persistResponse): void {
            $reservation = DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation || $reservation->status !== 'pending') {
                throw new RuntimeException('The AI token reservation is no longer active.');
            }

            DB::table('ai_assistant_budget_days')
                ->where('budget_date', $reservation->budget_date)
                ->lockForUpdate()
                ->first();

            $persistResponse();

            DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->update([
                    'status' => 'completed',
                    'actual_tokens' => max(0, $actualTokens),
                    'updated_at' => now(),
                ]);
        }, 3);
    }

    /**
     * Release a failed request's reservation. Idempotent for already-settled rows.
     */
    public static function release(int $reservationId): void
    {
        DB::transaction(function () use ($reservationId): void {
            $reservation = DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation || $reservation->status !== 'pending') {
                return;
            }

            DB::table('ai_assistant_budget_days')
                ->where('budget_date', $reservation->budget_date)
                ->lockForUpdate()
                ->first();

            DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->update([
                    'status' => 'released',
                    'updated_at' => now(),
                ]);
        }, 3);
    }
}
