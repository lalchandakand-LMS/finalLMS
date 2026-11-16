<?php

namespace Aloms\\UniversalAiAssistant;

final class AssistantTokenBudget
{
    public static function remaining(int $dailyBudget, int $usedTokens): int
    {
        return max(0, max(0, $dailyBudget) - max(0, $usedTokens));
    }

    /**
     * Conservative preflight estimate. Provider-reported usage remains authoritative.
     */
    public static function estimatePromptTokens(array $messages): int
    {
        $payload = json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return (int) ceil(mb_strlen($payload, 'UTF-8') / 3);
    }

    public static function completionLimit(int $configuredLimit, int $remainingTokens, int $estimatedPromptTokens): int
    {
        return max(0, min(
            max(0, $configuredLimit),
            max(0, $remainingTokens) - max(0, $estimatedPromptTokens)
        ));
    }
}
