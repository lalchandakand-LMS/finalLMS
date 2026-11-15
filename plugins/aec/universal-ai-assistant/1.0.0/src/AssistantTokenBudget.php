<?php

namespace Aloms\UniversalAiAssistant;

final class AssistantTokenBudget
{
    public static function remaining(int $dailyBudget, int $usedTokens): int
    {
        return max(0, $dailyBudget - max(0, $usedTokens));
    }

    /**
     * Estimate prompt tokens conservatively for preflight budget reservation.
     *
     * Provider usage remains authoritative after a response; this estimate only
     * prevents sending an unrestricted completion allowance when little budget remains.
     */
    public static function estimatePromptTokens(array $messages): int
    {
        $payload = json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return (int) ceil(mb_strlen($payload) / 3);
    }

    public static function completionLimit(int $configuredLimit, int $remainingTokens, int $estimatedPromptTokens): int
    {
        return max(0, min(
            max(0, $configuredLimit),
            max(0, $remainingTokens) - max(0, $estimatedPromptTokens)
        ));
    }
}
