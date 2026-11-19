<?php

namespace Tests\Unit;

use App\Services\Security\AdminTotpService;
use Tests\TestCase;

class AdminTotpServiceTest extends TestCase
{
    public function test_generated_totp_code_verifies_once_and_cannot_be_replayed(): void
    {
        $service = app(AdminTotpService::class);
        $secret = $service->generateSecret();
        $timestamp = 1_700_000_000;
        $code = $service->codeForTesting($secret, $timestamp);

        $step = $service->verify($secret, $code, $timestamp);
        $this->assertSame(intdiv($timestamp, 30), $step);
        $this->assertNull($service->verify($secret, $code, $timestamp, $step));
        $this->assertNull($service->verify($secret, $code, $timestamp + 120));
    }


    public function test_totp_matches_the_rfc_6238_sha1_test_vector(): void
    {
        $service = app(AdminTotpService::class);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $service->codeForTesting($secret, 59));
        $this->assertSame(1, $service->verify($secret, '287082', 59));
    }

    public function test_recovery_code_hash_is_case_and_separator_insensitive(): void
    {
        $service = app(AdminTotpService::class);

        $this->assertSame(
            $service->recoveryCodeHash('A1B2-C3D4-E5F6'),
            $service->recoveryCodeHash('a1b2 c3d4 e5f6'),
        );
        $this->assertNotSame(
            $service->recoveryCodeHash('A1B2-C3D4-E5F6'),
            $service->recoveryCodeHash('A1B2-C3D4-E5F7'),
        );
    }

    public function test_provisioning_uri_contains_issuer_account_and_secret(): void
    {
        $service = app(AdminTotpService::class);
        $uri = $service->provisioningUri('JBSWY3DPEHPK3PXP', 'admin@example.test');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('admin%40example.test', $uri);
        $this->assertStringContainsString('algorithm=SHA1', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
