<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SystemMaintenanceController extends Controller
{
    public function index(): View
    {
        $checks = [];
        $checks[] = $this->check('PHP version', PHP_VERSION_ID >= 80300, PHP_VERSION);
        $checks[] = $this->check('Production environment', app()->environment('production'), app()->environment());
        $checks[] = $this->check('Debug mode disabled', ! config('app.debug'), config('app.debug') ? 'APP_DEBUG is enabled' : 'Disabled');

        try {
            DB::connection()->getPdo();
            $checks[] = $this->check('Database connection', true, DB::connection()->getDriverName());
            $checks[] = $this->check('Migration history', Schema::hasTable('migrations'), Schema::hasTable('migrations') ? 'Migrations table found' : 'Migrations table missing');
        } catch (Throwable $exception) {
            $checks[] = $this->check('Database connection', false, 'Connection failed; inspect private application logs.');
            $checks[] = $this->check('Migration history', false, 'Unavailable until database connection succeeds.');
        }

        foreach ([
            'Application storage' => storage_path(),
            'Framework cache' => storage_path('framework/cache'),
            'Bootstrap cache' => base_path('bootstrap/cache'),
            'Private backup directory' => Storage::disk('local')->path('backups'),
        ] as $label => $path) {
            $exists = is_dir($path);
            $writable = $exists ? is_writable($path) : is_writable(dirname($path));
            $checks[] = $this->check($label, $writable, $writable ? 'Writable' : 'Not writable');
        }

        $backupFiles = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path) => str_ends_with($path, '.json.gz'));
        $latestBackup = $backupFiles->isEmpty()
            ? null
            : $backupFiles->map(fn (string $path) => Storage::disk('local')->lastModified($path))->max();

        $checks[] = $this->check('Recovery backup', $backupFiles->isNotEmpty(), $backupFiles->isNotEmpty()
            ? $backupFiles->count().' private backup archive(s); latest '.date('d M Y H:i', $latestBackup)
            : 'No backup archive found. Create one before updating.');

        $passed = collect($checks)->where('passed', true)->count();

        return view('admin.system.index', [
            'checks' => $checks,
            'passed' => $passed,
            'total' => count($checks),
            'laravelVersion' => app()->version(),
            'phpVersion' => PHP_VERSION,
            'appVersion' => config('app.version', env('APP_VERSION', 'Not tagged')),
            'backupCount' => $backupFiles->count(),
        ]);
    }

    private function check(string $label, bool $passed, string $detail): array
    {
        return compact('label', 'passed', 'detail');
    }
}
