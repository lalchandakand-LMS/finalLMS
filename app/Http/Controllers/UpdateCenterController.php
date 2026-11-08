<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class UpdateCenterController extends Controller
{
    public function index(): View
    {
        $migrationFiles = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $path) => basename($path, '.php'))
            ->sort()->values();

        $appliedMigrations = Schema::hasTable('migrations')
            ? DB::table('migrations')->pluck('migration')->all()
            : [];

        $pendingMigrations = $migrationFiles->diff($appliedMigrations)->values();
        $backupFiles = collect(Storage::disk('local')->allFiles('backups'))
            ->filter(fn (string $path) => str_ends_with($path, '.json.gz'))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => Storage::disk('local')->size($path),
                'modified' => Storage::disk('local')->lastModified($path),
            ])->sortByDesc('modified')->values();

        return view('admin.updates.index', [
            'currentVersion' => config('app.version', '1.0.0'),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'environment' => app()->environment(),
            'pendingMigrations' => $pendingMigrations,
            'backupFiles' => $backupFiles,
            'latestBackup' => $backupFiles->first(),
        ]);
    }

    public function migrate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confirmation' => ['required', 'in:MIGRATE'],
            'password' => ['required', 'string'],
        ]);

        abort_unless(Hash::check($validated['password'], $request->user()->password), 403, 'Administrator password confirmation failed.');

        try {
            app(BackupController::class)->create();

            $latestBackup = collect(Storage::disk('local')->allFiles('backups'))
                ->filter(fn (string $path) => str_ends_with($path, '.json.gz'))
                ->sortByDesc(fn (string $path) => Storage::disk('local')->lastModified($path))
                ->first();

            abort_unless($latestBackup && Storage::disk('local')->lastModified($latestBackup) >= now()->subMinutes(10)->timestamp, 500, 'A fresh pre-update backup could not be verified.');

            $exitCode = Artisan::call('migrate', ['--force' => true]);
            if ($exitCode !== 0) {
                return redirect()->route('admin.updates.index')
                    ->with('error', 'Migration command failed. A pre-update backup was created; inspect server logs before retrying.');
            }

            return redirect()->route('admin.updates.index')
                ->with('success', 'Database migrations completed. A pre-update backup is available in Backup & Restore.');
        } catch (Throwable $exception) {
            report($exception);
            return redirect()->route('admin.updates.index')
                ->with('error', 'Update preparation or migration failed. Check server logs and the pre-update backup before retrying.');
        }
    }
}
