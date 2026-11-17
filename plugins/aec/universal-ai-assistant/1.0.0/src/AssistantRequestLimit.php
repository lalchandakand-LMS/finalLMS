<?php

namespace Aloms\UniversalAiAssistant;

use Illuminate\Support\Facades\DB;

final class AssistantRequestLimit
{
    /**
     * Atomically consume one request from a user's daily allowance.
     * Failed provider calls still count, preventing repeated upstream abuse.
     */
    public static function reserve(int $userId, int $dailyLimit): bool
    {
        if ($userId < 1 || $dailyLimit < 1) {
            return false;
        }

        $date = today()->toDateString();

        return DB::transaction(function () use ($userId, $dailyLimit, $date): bool {
            $created = DB::table('ai_assistant_user_daily_requests')->insertOrIgnore([
                'user_id' => $userId,
                'request_date' => $date,
                'request_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ledger = DB::table('ai_assistant_user_daily_requests')
                ->where('user_id', $userId)
                ->where('request_date', $date)
                ->lockForUpdate()
                ->first();

            if ($created > 0 && $ledger) {
                $legacyRequests = (int) DB::table('ai_assistant_messages')
                    ->join('ai_assistant_conversations', 'ai_assistant_conversations.id', '=', 'ai_assistant_messages.conversation_id')
                    ->where('ai_assistant_conversations.user_id', $userId)
                    ->whereDate('ai_assistant_messages.created_at', $date)
                    ->where('ai_assistant_messages.message_role', 'user')
                    ->count();

                if ($legacyRequests > 0) {
                    DB::table('ai_assistant_user_daily_requests')
                        ->where('id', $ledger->id)
                        ->update(['request_count' => $legacyRequests, 'updated_at' => now()]);
                    $ledger->request_count = $legacyRequests;
                }
            }

            if (! $ledger || (int) $ledger->request_count >= $dailyLimit) {
                return false;
            }

            DB::table('ai_assistant_user_daily_requests')
                ->where('id', $ledger->id)
                ->update([
                    'request_count' => (int) $ledger->request_count + 1,
                    'updated_at' => now(),
                ]);

            return true;
        }, 3);
    }
}
