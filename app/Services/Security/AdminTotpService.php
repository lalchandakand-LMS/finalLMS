<?php

namespace App\Services\Security;

use InvalidArgumentException;

final class AdminTotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const STEP_SECONDS = 30;
    private const CODE_DIGITS = 6;

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(string $secret, string $email): string
    {
        $issuer = (string) config('app.name', 'Aloms LMS');
        $label = rawurlencode($issuer.':'.$email);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::CODE_DIGITS,
            'period' => self::STEP_SECONDS,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function verify(string $secret, string $code, ?int $timestamp = null, ?int $lastUsedStep = null): ?int
    {
        if (preg_match('/^[0-9]{6}$/D', $code) !== 1) {
            return null;
        }

        try {
            $key = $this->base32Decode($secret);
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($key === '') {
            return null;
        }

        $currentStep = intdiv($timestamp ?? time(), self::STEP_SECONDS);
        for ($step = $currentStep - 1; $step <= $currentStep + 1; $step++) {
            if ($step < 0 || ($lastUsedStep !== null && $step <= $lastUsedStep)) {
                continue;
            }

            $counter = pack('N2', 0, $step);
            $digest = hash_hmac('sha1', $counter, $key, true);
            $offset = ord($digest[strlen($digest) - 1]) & 0x0f;
            $binary = unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff;
            $expected = str_pad((string) ($binary % (10 ** self::CODE_DIGITS)), self::CODE_DIGITS, '0', STR_PAD_LEFT);

            if (hash_equals($expected, $code)) {
                return $step;
            }
        }

        return null;
    }

    public function codeForTesting(string $secret, ?int $timestamp = null): string
    {
        $key = $this->base32Decode($secret);
        $step = intdiv($timestamp ?? time(), self::STEP_SECONDS);
        $digest = hash_hmac('sha1', pack('N2', 0, $step), $key, true);
        $offset = ord($digest[strlen($digest) - 1]) & 0x0f;
        $binary = unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($binary % (10 ** self::CODE_DIGITS)), self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    /** @return list<string> Plaintext recovery codes; display once and never persist. */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($index = 0; $index < 8; $index++) {
            $raw = strtoupper(bin2hex(random_bytes(6)));
            $codes[] = substr($raw, 0, 4).'-'.substr($raw, 4, 4).'-'.substr($raw, 8, 4);
        }

        return $codes;
    }

    public function recoveryCodeHash(string $code): string
    {
        $normalized = strtoupper(preg_replace('/[^A-F0-9]/', '', $code) ?? '');

        return hash('sha256', $normalized);
    }

    private function base32Encode(string $binary): string
    {
        $buffer = 0;
        $bits = 0;
        $output = '';

        foreach (str_split($binary) as $character) {
            $buffer = ($buffer << 8) | ord($character);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $output .= self::BASE32_ALPHABET[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }

        if ($bits > 0) {
            $output .= self::BASE32_ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $output;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[\s=-]/', '', $encoded) ?? '');
        if ($encoded === '' || preg_match('/^[A-Z2-7]+$/D', $encoded) !== 1) {
            throw new InvalidArgumentException('Invalid Base32 secret.');
        }

        $buffer = 0;
        $bits = 0;
        $output = '';

        foreach (str_split($encoded) as $character) {
            $value = strpos(self::BASE32_ALPHABET, $character);
            if ($value === false) {
                throw new InvalidArgumentException('Invalid Base32 secret.');
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xff);
            }
            $buffer &= (1 << $bits) - 1;
        }

        return $output;
    }
}
