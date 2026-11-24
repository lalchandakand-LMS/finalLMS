<?php

namespace App\Modules\Ai;

use LogicException;

/**
 * Redacts credential-shaped words before they are emitted to an SSE client.
 *
 * A complete whitespace-delimited word is buffered so credentials split across
 * provider chunks are never partially written to the browser.
 */
final class AssistantStreamRedactor
{
    private string $pending = '';
    private bool $awaitingBearer = false;
    private string $bearerPrefix = '';
    private string $bearerWhitespace = '';
    private bool $finished = false;

    public function push(string $chunk): string
    {
        if ($this->finished) {
            throw new LogicException('The assistant stream redactor has already been finished.');
        }

        $this->pending .= $chunk;

        return $this->drain(false);
    }

    public function finish(): string
    {
        if ($this->finished) {
            return '';
        }

        $this->finished = true;

        return $this->drain(true);
    }

    private function drain(bool $final): string
    {
        $output = '';

        while ($this->pending !== '') {
            if (preg_match('/^\s+/u', $this->pending, $whitespace) === 1) {
                if ($this->awaitingBearer) {
                    $this->bearerWhitespace .= $whitespace[0];
                } else {
                    $output .= $whitespace[0];
                }

                $this->pending = substr($this->pending, strlen($whitespace[0]));
                continue;
            }

            if (preg_match('/^\S+/u', $this->pending, $wordMatch) !== 1) {
                break;
            }

            $word = $wordMatch[0];
            $wordBytes = strlen($word);
            $hasDelimiter = strlen($this->pending) > $wordBytes;
            if (! $hasDelimiter && ! $final) {
                break;
            }

            $this->pending = substr($this->pending, $wordBytes);

            if ($this->awaitingBearer) {
                $redactedWord = preg_replace(
                    '/^([^A-Za-z0-9._-]*)([A-Za-z0-9._-]+)/u',
                    '$1[REDACTED]',
                    $word,
                    1
                ) ?? '[REDACTED]';
                $output .= $this->bearerPrefix.'Bearer'.$this->bearerWhitespace.$redactedWord;
                $this->awaitingBearer = false;
                $this->bearerPrefix = '';
                $this->bearerWhitespace = '';
                continue;
            }

            if (preg_match('/^(.*)(Bearer)$/i', $word, $bearerMatch) === 1) {
                $this->awaitingBearer = true;
                $this->bearerPrefix = $bearerMatch[1] ?? '';
                continue;
            }

            $output .= self::redactCredentialTokens($word);
        }

        if ($final) {
            if ($this->awaitingBearer) {
                $output .= $this->bearerPrefix.'Bearer'.$this->bearerWhitespace.'[REDACTED]';
                $this->awaitingBearer = false;
                $this->bearerPrefix = '';
                $this->bearerWhitespace = '';
            }

            if ($this->pending !== '') {
                $output .= self::redactCredentialTokens($this->pending);
                $this->pending = '';
            }
        }

        return $output;
    }

    private static function redactCredentialTokens(string $value): string
    {
        return preg_replace(
            '/\b(sk-[A-Za-z0-9_-]{16,}|gh[pousr]_[A-Za-z0-9]{20,}|xox[baprs]-[A-Za-z0-9-]{10,}|AKIA[0-9A-Z]{16})\b/',
            '[REDACTED_SECRET]',
            $value
        ) ?? $value;
    }
}
