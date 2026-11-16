<?php

namespace App\Services;

use App\Models\SystemUpdateLock;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class UpdateOperationLock
{
    private const LOCK_ID = 1;

    private const LEASE_MINUTES = 90;

    /**
     * Run one state-changing update operation under a database-backed lease.
     *
     * The fixed row and conditional UPDATE make acquisition atomic across PHP
     * workers and browser sessions; the lease expires if a request is killed.
     */
    public function run(string $operation, User $admin, Closure $callback): mixed
    {
        $token = $this->acquire($operation, $admin);

        try {
            return $callback();
        } finally {
            $this->release($token);
        }
    }

    public function current(): ?SystemUpdateLock
    {
        return SystemUpdateLock::query()->find(self::LOCK_ID);
    }

    private function acquire(string $operation, User $admin): string
    {
        if (! preg_match('/^[a-z][a-z0-9_]{1,63}$/', $operation)) {
            throw new RuntimeException('The update operation identifier is invalid.');
        }

        $token = Str::random(64);
        $now = now();
        $updated = DB::table('system_update_locks')
            ->where('id', self::LOCK_ID)
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '<=', $now);
            })
            ->update([
                'operation' => $operation,
                'token' => $token,
                'locked_by' => $admin->id,
                'acquired_at' => $now,
                'expires_at' => $now->copy()->addMinutes(self::LEASE_MINUTES),
                'updated_at' => $now,
            ]);

        if ($updated !== 1) {
            $active = $this->current();
            $until = $active?->expires_at?->format('d M Y H:i:s');

            throw new RuntimeException(
                'Another update operation is already running'
                .($active?->operation ? ' ('.$active->operation.')' : '')
                .($until ? ' until '.$until : '')
                .'. Wait for it to finish or for its safety lease to expire.'
            );
        }

        return $token;
    }

    private function release(string $token): void
    {
        DB::table('system_update_locks')
            ->where('id', self::LOCK_ID)
            ->where('token', $token)
            ->update([
                'operation' => null,
                'token' => null,
                'locked_by' => null,
                'acquired_at' => null,
                'expires_at' => null,
                'updated_at' => now(),
            ]);
    }
}
