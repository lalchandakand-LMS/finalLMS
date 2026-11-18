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

        // A byte-based upper estimate intentionally over-reserves for multilingual
        // input and escaped JSON. Refuse unbounded context before reserving usage.
        $bytes = strlen($payload);
        if ($bytes > 524288) {
            throw new RuntimeException('The AI prompt exceeded the configured size limit.');
        }

        return max(1, (int) ceil($bytes * 0.75));
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
        int $dailyRequestLimit,
        string $assistantRole,
        string $provider,
        string $model,
        int $userId,
        int $conversationId
    ): ?array {
        $date = today()->toDateString();

        return DB::transaction(function () use (
            $date, $dailyBudget, $estimatedPromptTokens, $configuredCompletionLimit, $dailyRequestLimit, $assistantRole, $provider, $model, $userId, $conversationId
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

            // An expired in-flight request may already have been accepted and billed
            // by the provider. Keep its full reservation charged until reconciled.
            DB::table('ai_assistant_token_reservations')
                ->where('budget_date', $date)
                ->where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'uncertain', 'updated_at' => now()]);

            $todayRequests = (int) DB::table('ai_assistant_messages')
                ->join('ai_assistant_conversations', 'ai_assistant_conversations.id', '=', 'ai_assistant_messages.conversation_id')
                ->where('ai_assistant_conversations.user_id', $userId)
                ->where('ai_assistant_messages.message_role', 'user')
                ->whereDate('ai_assistant_messages.created_at', $date)
                ->count();

            $pendingRequests = (int) DB::table('ai_assistant_token_reservations')
                ->where('budget_date', $date)
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'uncertain'])
                ->where('prompt_persisted', false)
                ->count();

            if ($todayRequests + $pendingRequests + 1 > max(0, $dailyRequestLimit)) {
                return null;
            }

            $usedTokens = (int) DB::table('ai_assistant_messages')
                ->where('message_role', 'assistant')
                ->whereDate('created_at', $date)
                ->sum('token_count');

            $pendingTokens = (int) DB::table('ai_assistant_token_reservations')
                ->where('budget_date', $date)
                ->whereIn('status', ['pending', 'uncertain'])
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
                'assistant_role' => $assistantRole,
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => max(1, $estimatedPromptTokens),
                'completion_limit' => $completionLimit,
                'status' => 'pending',
                'actual_tokens' => null,
                'prompt_persisted' => false,
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['id' => (int) $id, 'completion_limit' => $completionLimit];
        }, 3);
    }

    /**
     * Persist a user prompt and mark its request reservation in one transaction.
     */
    public static function persistPrompt(int $reservationId, callable $persistPrompt): void
    {
        $persisted = DB::transaction(function () use ($reservationId, $persistPrompt): bool {
            $reservation = DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation || ! in_array($reservation->status, ['pending', 'uncertain'], true)) {
                return false;
            }

            $persistPrompt();

            DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->update([
                    'prompt_persisted' => true,
                    'updated_at' => now(),
                ]);

            return true;
        }, 3);

        if (! $persisted) {
            throw new RuntimeException('The AI token reservation is no longer active or has expired.');
        }
    }

    /**
     * Persist the assistant response and consume its reservation atomically.
     */
    public static function settle(int $reservationId, int $actualTokens, callable $persistResponse): void
    {
        $settled = DB::transaction(function () use ($reservationId, $actualTokens, $persistResponse): bool {
            $budgetDate = DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->value('budget_date');
            if (! $budgetDate) return false;

            DB::table('ai_assistant_budget_days')
                ->where('budget_date', $budgetDate)
                ->lockForUpdate()
                ->first();

            $reservation = DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation || ! in_array($reservation->status, ['pending', 'uncertain'], true)) {
                return false;
            }

            $persistResponse();

            $actualTokens = max(0, $actualTokens);
            $reservedCeiling = max(0, (int) $reservation->prompt_tokens) + max(0, (int) $reservation->completion_limit);
            DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->update([
                    'status' => $actualTokens > $reservedCeiling ? 'over_budget' : 'completed',
                    'actual_tokens' => $actualTokens,
                    'updated_at' => now(),
                ]);

            return true;
        }, 3);

        if (! $settled) {
            throw new RuntimeException('The AI token reservation is no longer active or has expired.');
        }
    }

    /**
     * Preserve the full reservation when provider acceptance or billing is unknown.
     */
    public static function markUncertain(int $reservationId): void
    {
        DB::transaction(function () use ($reservationId): void {
            $budgetDate = DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->value('budget_date');
            if (! $budgetDate) return;

            DB::table('ai_assistant_budget_days')
                ->where('budget_date', $budgetDate)
                ->lockForUpdate()
                ->first();

            $reservation = DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation || ! in_array($reservation->status, ['pending', 'uncertain'], true)) {
                return;
            }

            DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->update([
                    'status' => 'uncertain',
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
            $budgetDate = DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->value('budget_date');
            if (! $budgetDate) return;

            DB::table('ai_assistant_budget_days')
                ->where('budget_date', $budgetDate)
                ->lockForUpdate()
                ->first();

            $reservation = DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation || ! in_array($reservation->status, ['pending', 'uncertain'], true)) {
                return;
            }

            DB::table('ai_assistant_token_reservations')
                ->where('id', $reservationId)
                ->update([
                    'status' => 'released',
                    'updated_at' => now(),
                ]);
        }, 3);
    }
}
