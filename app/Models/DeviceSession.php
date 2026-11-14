<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSession extends Model
{
    protected $fillable = [
        'user_id', 'student_device_id', 'session_hash', 'device_name',
        'user_agent', 'ip_address', 'logged_in_at', 'last_seen_at',
        'revoked_at', 'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trustedDevice(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class, 'student_device_id');
    }

    public static function register($request, User $user, ?StudentDevice $device = null): self
    {
        $sessionHash = hash('sha256', $request->session()->getId());
        $request->session()->put('device_session_hash', $sessionHash);

        return self::updateOrCreate(
            ['session_hash' => $sessionHash],
            [
                'user_id' => $user->id,
                'student_device_id' => $device?->id,
                'device_name' => $device?->device_name ?: self::describeAgent($request->userAgent()),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'ip_address' => $request->ip(),
                'logged_in_at' => now(),
                'last_seen_at' => now(),
                'revoked_at' => null,
                'revoke_reason' => null,
            ]
        );
    }

    private static function describeAgent(?string $userAgent): string
    {
        $agent = strtolower((string) $userAgent);
        $browser = str_contains($agent, 'edg/') ? 'Edge' : (str_contains($agent, 'chrome/') ? 'Chrome' : (str_contains($agent, 'firefox/') ? 'Firefox' : (str_contains($agent, 'safari/') ? 'Safari' : 'Unknown browser')));
        $platform = str_contains($agent, 'android') ? 'Android' : (str_contains($agent, 'iphone') || str_contains($agent, 'ipad') ? 'iOS' : (str_contains($agent, 'windows') ? 'Windows' : (str_contains($agent, 'mac os') ? 'macOS' : (str_contains($agent, 'linux') ? 'Linux' : 'Unknown device'))));

        return $platform.' · '.$browser;
    }
}
