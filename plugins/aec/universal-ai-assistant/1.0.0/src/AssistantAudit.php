<?php

namespace Aloms\\UniversalAiAssistant;

use Illuminate\\Support\\Facades\\DB;

final class AssistantAudit
{
    public static function record(
        string $event,
        ?int $userId,
        ?int $conversationId,
        ?int $reservationId,
        string $role,
        string $provider,
        string $model,
        array $metadata = []
    ): void {
        DB::table('ai_assistant_audit_events')->insert([
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'reservation_id' => $reservationId,
            'assistant_role' => $role,
            'provider' => $provider,
            'model' => $model,
            'event' => $event,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
