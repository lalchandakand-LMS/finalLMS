<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AdminSystemHealthController extends Controller
{
    public function index(): View
    {
        $checks = DB::table('system_health_checks')->latest('checked_at')->limit(20)->get();
        $schedulerHeartbeat = DB::table('system_health_heartbeats')->where('name', 'scheduler')->value('last_seen_at');

        return view('admin.system-health.index', compact('checks', 'schedulerHeartbeat'));
    }

    public function run(): RedirectResponse
    {
        $startedAt = microtime(true);
        $results = [];

        $results['database'] = $this->probe(fn () => DB::select('SELECT 1'));
        $results['cache'] = $this->probe(function (): void {
            $key = 'system-health-probe:' . Str::uuid();
            Cache::put($key, 'ok', 30);
            try {
                if (Cache::get($key) !== 'ok') {
                    throw new \RuntimeException('Cache read-after-write failed.');
                }
            } finally {
                Cache::forget($key);
            }
        });
        $results['storage'] = $this->probe(function (): void {
            $disk = Storage::disk();
            $path = '.system-health/' . Str::uuid() . '.tmp';
            try {
                if (!$disk->put($path, 'ok') || $disk->get($path) !== 'ok') {
                    throw new \RuntimeException('Storage write/read verification failed.');
                }
            } finally {
                $disk->delete($path);
            }
        });
        $results['queue_table'] = $this->probe(function (): void {
            if (config('queue.default') === 'database' && !Schema::hasTable('jobs')) {
                throw new \RuntimeException('The configured database queue table is missing.');
            }
        });

        $failed = collect($results)->contains(fn (array $result) => $result['status'] !== 'ok');
        $duration = (int) round((microtime(true) - $startedAt) * 1000);

        DB::table('system_health_checks')->insert([
            'status' => $failed ? 'degraded' : 'healthy',
            'results' => json_encode($results, JSON_THROW_ON_ERROR),
            'duration_ms' => $duration,
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('system_health_checks')->where('checked_at', '<', now()->subDays(30))->delete();

        return redirect()->route('admin.system-health.index')
            ->with($failed ? 'error' : 'success', $failed
                ? 'Health check completed. Review the failed components below.'
                : 'All configured application health checks passed.');
    }

    private function probe(callable $callback): array
    {
        $startedAt = microtime(true);

        try {
            $callback();

            return ['status' => 'ok', 'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000)];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'status' => 'failed',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'message' => 'Check failed. Review the protected application log for details.',
            ];
        }
    }
}
