<?php

namespace App\Support;

final class SafeWebsiteUrl
{
    public static function sanitize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value !== trim($value)) {
            return null;
        }

        // Reject control/whitespace bytes, backslashes and encoded variants that
        // browsers or proxies may normalize differently.
        if (preg_match('/[\\x00-\\x20\\x7f]/', $value) === 1
            || str_contains($value, '\\')
            || preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|7f|5c)/i', $value) === 1) {
            return null;
        }

        if ($value[0] === '#') {
            return preg_match('/^#[A-Za-z0-9][A-Za-z0-9_.:-]{0,199}$/D', $value) === 1
                ? $value
                : null;
        }

        if ($value[0] === '/') {
            if (str_starts_with($value, '//')) {
                return null;
            }

            $parts = parse_url($value);
            if (! is_array($parts)
                || isset($parts['scheme'])
                || isset($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])) {
                return null;
            }

            return $value;
        }

        $parts = parse_url($value);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || ! is_string($parts['host'] ?? null)
            || $parts['host'] === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $value;
    }
}
