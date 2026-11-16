<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('system:heartbeat', function (): int {
    DB::table('system_health_heartbeats')->updateOrInsert(
        ['name' => 'scheduler'],
        ['last_seen_at' => now(), 'updated_at' => now(), 'created_at' => now()],
    );

    $this->info('Scheduler heartbeat recorded.');

    return self::SUCCESS;
})->purpose('Record the LMS scheduler heartbeat');

Schedule::command('system:heartbeat')->everyFiveMinutes()->withoutOverlapping();
