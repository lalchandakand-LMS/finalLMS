<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('system:heartbeat', function (): int {
    DB::table('system_health_heartbeats')->updateOrInsert(
        ['name' => 'scheduler'],
        ['last_seen_at' => now(), 'updated_at' => now(), 'created_at' => now()],
    );

    $this->info('Scheduler heartbeat recorded.');

    return 0;
})->purpose('Record the LMS scheduler heartbeat');

Artisan::command('system:health-check', function (): int {
    $startedAt = microtime(true);
    $results = [];

    $probe = function (string $name, callable $callback) use (&$results): void {
        $started = microtime(true);
        try {
            $details = $callback();
            $results[$name] = [
                'status' => 'ok',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'details' => $details,
            ];
        } catch (Throwable $exception) {
            report($exception);
            $results[$name] = [
                'status' => 'failed',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'message' => 'Component check failed. Review protected application logs.',
            ];
        }
    };

    $probe('database', function (): string {
        try {
            DB::select('SELECT 1');
        } catch (Throwable $exception) {
            DB::purge();
            DB::reconnect();
            DB::select('SELECT 1');
            return 'Database connection recovered after one reconnect.';
        }

        return 'Database query succeeded.';
    });

    $probe('cache', function (): string {
        $key = 'system-health-scheduled:' . Str::uuid();
        Cache::put($key, 'ok', 30);
        try {
            if (Cache::get($key) !== 'ok') {
                throw new RuntimeException('Cache read-after-write failed.');
            }
        } finally {
            Cache::forget($key);
        }

        return 'Cache read/write succeeded.';
    });

    $probe('storage', function (): string {
        $disk = Storage::disk();
        $path = '.system-health/' . Str::uuid() . '.tmp';
        try {
            if (!$disk->put($path, 'ok') || $disk->get($path) !== 'ok') {
                throw new RuntimeException('Storage read/write failed.');
            }
        } finally {
            $disk->delete($path);
        }

        return 'Default storage read/write succeeded.';
    });

    $probe('queue', function (): string {
        if (config('queue.default') === 'database' && !Schema::hasTable('jobs')) {
            throw new RuntimeException('Configured database queue table is missing.');
        }

        return 'Configured queue storage is present.';
    });

    $failed = collect($results)->contains(fn (array $result) => $result['status'] === 'failed');
    $duration = (int) round((microtime(true) - $startedAt) * 1000);

    try {
        DB::table('system_health_checks')->insert([
            'status' => $failed ? 'degraded' : 'healthy',
            'results' => json_encode($results, JSON_THROW_ON_ERROR),
            'duration_ms' => $duration,
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('system_health_checks')->where('checked_at', '<', now()->subDays(30))->delete();
    } catch (Throwable $exception) {
        report($exception);
        $failed = true;
        $results['health_history'] = [
            'status' => 'failed',
            'duration_ms' => 0,
            'message' => 'Health history persistence or retention cleanup failed. Inspect protected application logs.',
        ];
    }

    if ($failed) {
        $recipients = config('system_health.alert_emails', []);
        $shouldSend = false;
        try {
            $shouldSend = Cache::store('file')->add(
                'system-health:alert-cooldown',
                true,
                now()->addMinutes(config('system_health.alert_cooldown_minutes', 30)),
            );
        } catch (Throwable $exception) {
            report($exception);
            // If the cooldown store is unavailable, prefer one alert over silently suppressing an incident.
            $shouldSend = true;
        }

        if ($shouldSend && $recipients !== []) {
            $failedNames = collect($results)->filter(fn (array $result) => $result['status'] === 'failed')->keys()->implode(', ');
            $body = "Alom's English Center LMS health check detected a degraded component.\n"
                . "Failed checks: {$failedNames}\n"
                . "Check the protected Admin > Website health page and application logs. No payment or student data was modified.";

            foreach ($recipients as $recipient) {
                try {
                    Mail::raw($body, function ($message) use ($recipient): void {
                        $message->to($recipient)->subject('LMS website health alert');
                    });
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        } elseif ($shouldSend) {
            Log::warning('LMS health check failed but SYSTEM_HEALTH_ALERT_EMAILS is not configured.');
        }

        $this->warn('Health check detected a degraded component.');

        return 1;
    }

    try {
        Cache::store('file')->forget('system-health:alert-cooldown');
    } catch (Throwable $exception) {
        report($exception);
    }

    $this->info('All scheduled health checks passed.');

    return 0;
})->purpose('Check application dependencies and send rate-limited email alerts');

Artisan::command('referral-income:reconcile-approved-payments', function (): int {
    $tables = ['referral_attributions', 'referral_income_rules', 'referral_commissions'];
    if (!class_exists(\Aloms\ReferralIncome\ReferralIncomeService::class)
        || ! Schema::hasTable('plugin_registry')
        || ! DB::table('plugin_registry')->where('plugin_key', 'aec.referral-income')->where('status', 'active')->exists()
        || collect($tables)->contains(fn (string $table): bool => !Schema::hasTable($table))) {
        $this->info('Referral income is not active; no reconciliation is required.');

        return 0;
    }

    $service = app(\Aloms\ReferralIncome\ReferralIncomeService::class);
    $processed = 0;
    $failed = 0;

    // The approved Payment row is the durable source of truth. If a synchronous
    // commission write failed after payment approval, this query finds it again
    // on the next scheduler run. recordApprovedPayment() is idempotent.
    DB::table('payments as p')
        ->select('p.id')
        ->where('p.status', 'approved')
        ->where('p.amount', '>', 0)
        ->whereRaw('UPPER(p.currency) = ?', ['BDT'])
        ->whereNull('p.learn_earn_exam_id')
        ->where(function ($query): void {
            $query->whereRaw("TRIM(COALESCE(p.gateway_provider, '')) = ''")
                ->orWhere(function ($gateway): void {
                    $gateway->where('p.gateway_provider', 'bkash')
                        ->whereRaw("TRIM(COALESCE(p.gateway_transaction_id, '')) <> ''");
                });
        })
        ->whereExists(function ($query): void {
            $query->selectRaw('1')->from('referral_attributions as ra')
                ->whereColumn('ra.referred_user_id', 'p.user_id')
                ->where('ra.status', 'active')
                ->whereExists(function ($rules): void {
                    $rules->selectRaw('1')->from('referral_income_rules as rir')
                        ->whereColumn('rir.owner_type', 'ra.referrer_type')
                        ->where('rir.is_active', true)
                        ->where('rir.commission_percent', '>', 0)
                        ->whereRaw('ROUND(p.amount * rir.commission_percent / 100, 2) > 0');
                });
        })
        ->whereNotExists(function ($query): void {
            $query->selectRaw('1')->from('referral_commissions as rc')
                ->whereColumn('rc.payment_id', 'p.id');
        })
        ->orderBy('p.id')
        ->chunkById(100, function ($payments) use ($service, &$processed, &$failed): void {
            foreach ($payments as $payment) {
                try {
                    $service->recordApprovedPayment((int) $payment->id);
                    $processed++;
                } catch (Throwable $exception) {
                    // Do not log payment or student data; the next run retries
                    // because no commission row was committed.
                    report($exception);
                    $failed++;
                }
            }
        }, 'p.id', 'id');

    $this->info("Reconciled {$processed} eligible approved payment(s).");
    if ($failed > 0) {
        $this->warn("{$failed} payment(s) could not be reconciled and will be retried.");
        return 1;
    }

    return 0;
})->purpose('Retry missing referral commissions for approved payments');

Schedule::command('referral-income:reconcile-approved-payments')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('system:heartbeat')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('system:health-check')->everyFiveMinutes()->withoutOverlapping();
