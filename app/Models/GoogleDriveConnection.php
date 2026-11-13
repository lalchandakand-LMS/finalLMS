<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class GoogleDriveConnection extends Model
{
    protected $fillable = [
        'google_email', 'access_token', 'refresh_token',
        'token_expires_at', 'is_active', 'last_checked_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function accessTokenValue(): ?string
    {
        return $this->decryptToken($this->access_token);
    }

    public function refreshTokenValue(): ?string
    {
        return $this->decryptToken($this->refresh_token);
    }

    public function setEncryptedTokens(string $accessToken, ?string $refreshToken, int $expiresIn): void
    {
        $values = [
            'access_token' => Crypt::encryptString($accessToken),
            'token_expires_at' => now()->addSeconds(max(60, $expiresIn - 60)),
        ];

        if ($refreshToken !== null && $refreshToken !== '') {
            $values['refresh_token'] = Crypt::encryptString($refreshToken);
        }

        $this->forceFill($values)->save();
    }

    private function decryptToken(?string $token): ?string
    {
        if (!$token) return null;

        try {
            return Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }
    }
}
