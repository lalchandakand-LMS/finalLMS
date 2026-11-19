<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Existing feature tests represent an already authenticated administrator
     * session. Dedicated MFA tests use actingAsAdminWithoutMfa() to exercise the
     * mandatory challenge boundary explicitly.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User && $user->isAdmin()) {
            if (! $user->admin_totp_enabled_at || ! $user->admin_totp_secret) {
                $user->forceFill([
                    'admin_totp_secret' => 'JBSWY3DPEHPK3PXP',
                    'admin_totp_enabled_at' => now(),
                    'admin_totp_recovery_codes' => [],
                ])->save();
            }

            $this->withSession(['admin_mfa_verified_at' => now()->timestamp]);
        }

        return $this;
    }

    public function actingAsAdminWithoutMfa(User $admin): static
    {
        parent::actingAs($admin);
        $this->withSession(['admin_mfa_verified_at' => null]);

        return $this;
    }
}
