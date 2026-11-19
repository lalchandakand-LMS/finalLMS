<?php

namespace Aloms\UniversalAiAssistant;

/**
 * Incrementally redacts supported credential patterns without emitting a
 * partial token split across provider chunks.
 */
final class AssistantStreamRedactor
{
    private string $buffer = '';

    public function push(string $chunk): string
    {
        $this->buffer .= $chunk;

        return $this->drain(false);
    }

    public function finish(): string
    {
        return $this->drain(true);
    }

    private function drain(bool $final): string
    {
        $output = '';

        while ($this->buffer !== '') {
            if (preg_match('/(?i:Bearer\s+)|\b(?:sk-|gh[pousr]_|xox[baprs]-|AKIA)/', $this->buffer, $match, PREG_OFFSET_CAPTURE) !== 1) {
                if ($final) {
                    $output .= $this->buffer;
                    $this->buffer = '';
                    break;
                }

                // Keep enough overlap to detect a credential prefix split over
                // the next provider chunk. Complete token candidates are held
                // from their prefix until a delimiter arrives.
                $safeLength = max(0, strlen($this->buffer) - 16);
                $output .= substr($this->buffer, 0, $safeLength);
                $this->buffer = substr($this->buffer, $safeLength);
                break;
            }

            $start = $match[0][1];
            if ($start > 0) {
                $output .= substr($this->buffer, 0, $start);
                $this->buffer = substr($this->buffer, $start);
            }

            $prefix = $match[0][0];
            $tokenOffset = strlen($prefix);
            if (stripos($prefix, 'Bearer') === 0) {
                $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789._-';
                $minimum = 1;
                $replacement = $prefix.'[REDACTED]';
            } elseif ($prefix === 'sk-') {
                $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';
                $minimum = 16;
                $replacement = '[REDACTED_SECRET]';
            } elseif (str_starts_with($prefix, 'gh')) {
                $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                $minimum = 20;
                $replacement = '[REDACTED_SECRET]';
            } elseif (str_starts_with($prefix, 'xox')) {
                $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-';
                $minimum = 10;
                $replacement = '[REDACTED_SECRET]';
            } else {
                $charset = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $minimum = 16;
                $replacement = '[REDACTED_SECRET]';
            }

            $tokenLength = strspn($this->buffer, $charset, $tokenOffset);
            $tokenEndsAtBufferEnd = ($tokenOffset + $tokenLength) === strlen($this->buffer);
            if ($tokenEndsAtBufferEnd && ! $final) {
                break;
            }

            $original = substr($this->buffer, 0, $tokenOffset + $tokenLength);
            $output .= $tokenLength >= $minimum ? $replacement : $original;
            $this->buffer = substr($this->buffer, $tokenOffset + $tokenLength);
        }

        return $output;
    }
}
