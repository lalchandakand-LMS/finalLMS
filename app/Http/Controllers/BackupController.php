<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class BackupController extends Controller
{
    private const IGNORED_TABLES = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];


    private function backupTableNames(): array
    {
        return array_values(array_filter(
            $this->tableNames(),
            fn (string $table) => ! in_array($table, self::IGNORED_TABLES, true)
        ));
    }

    private function tableNames(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select('SHOW TABLES');
            return array_map(fn ($row) => (string) array_values((array) $row)[0], $rows);
        }

        if ($driver === 'sqlite') {
            return array_map(
                fn ($row) => (string) $row->name,
                DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
            );
        }

        if ($driver === 'pgsql') {
            return array_map(
                fn ($row) => (string) $row->tablename,
                DB::select("SELECT tablename FROM pg_tables WHERE schemaname = current_schema()")
            );
        }

        throw new \RuntimeException('Database driver is not supported by backup and restore.');
    }

    public function index(): View
    {
        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path) => str_ends_with($path, '.json.gz'))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => Storage::disk('local')->size($path),
                'modified' => Storage::disk('local')->lastModified($path),
            ])->sortByDesc('modified')->values();

        return view('admin.backups.index', compact('files'));
    }

    public function create(): RedirectResponse
    {
        $filename = $this->writeBackupArchive('manual');

        return redirect()->route('admin.backups.index')->with('success', 'Database and uploaded media backup created: '.$filename);
    }

    /**
     * Create a signed, private recovery point before any destructive restore.
     * The archive is stored on the local private disk, never under public storage.
     */
    private function writeBackupArchive(string $kind): string
    {
        $tableNames = $this->backupTableNames();
        sort($tableNames);
        $tables = [];
        $schema = [];

        foreach ($tableNames as $table) {
            $tables[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            $schema[$table] = Schema::getColumnListing($table);
        }

        $mediaFiles = [];
        if (in_array('media_assets', $tableNames, true)) {
            foreach ($tables['media_assets'] as $asset) {
                $disk = $asset['disk'] ?? 'public';
                $path = $asset['path'] ?? '';
                if ($path !== '' && Storage::disk($disk)->exists($path)) {
                    $mediaFiles[$path] = base64_encode(Storage::disk($disk)->get($path));
                }
            }
        }

        $privateFiles = [];
        foreach (Storage::disk('local')->allFiles('assignment-submissions') as $privatePath) {
            $privateFiles[$privatePath] = base64_encode(Storage::disk('local')->get($privatePath));
        }

        $payload = json_encode([
            'format_version' => 1,
            'backup_kind' => $kind,
            'created_at' => now()->toIso8601String(),
            'schema_fingerprint' => hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)),
            'tables' => $tables,
            'public_media' => $mediaFiles,
            'private_files' => $privateFiles,
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));
        $archive = gzencode(json_encode([
            'payload' => base64_encode($payload),
            'signature' => $signature,
        ], JSON_THROW_ON_ERROR), 9);

        if (! is_string($archive)) {
            throw new RuntimeException('Unable to compress the backup archive.');
        }

        $filename = 'aloms-lms-backup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(5)).'.json.gz';
        Storage::disk('local')->put('backups/'.$filename, $archive);

        return $filename;
    }

    public function download(string $filename)
    {
        abort_unless($filename === basename($filename) && preg_match('/^aloms-lms-backup-[0-9]{8}-[0-9]{6}-[a-z0-9]{5}\.json\.gz$/', $filename), 404);
        $path = 'backups/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $filename, ['Content-Type' => 'application/gzip']);
    }

    public function restore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'backup_file' => ['required', 'file', 'max:102400'],
            'confirmation' => ['required', 'in:RESTORE'],
            'password' => ['required', 'string'],
        ]);

        abort_unless(Hash::check($validated['password'], $request->user()->password), 403, 'Password confirmation failed.');

        $compressed = file_get_contents($validated['backup_file']->getRealPath());
        try {
            $envelopeJson = $compressed !== false ? @gzdecode($compressed, 536870912) : false;
        } catch (Throwable $exception) {
            $envelopeJson = false;
        }
        abort_unless(is_string($envelopeJson), 422, 'Invalid or damaged backup archive.');

        try {
            $envelope = json_decode($envelopeJson, true, 512, JSON_THROW_ON_ERROR);
            $payloadJson = base64_decode($envelope['payload'] ?? '', true);
            abort_unless(is_string($payloadJson), 422, 'Backup payload is invalid.');
            $expected = hash_hmac('sha256', $payloadJson, (string) config('app.key'));
            abort_unless(is_string($envelope['signature'] ?? null) && hash_equals($expected, $envelope['signature']), 422, 'Backup signature is invalid.');

            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(($payload['format_version'] ?? null) === 1 && is_array($payload['tables'] ?? null), 422, 'Unsupported backup format.');

            $currentTables = $this->backupTableNames();
            sort($currentTables);

            // Keep a fresh recovery point before the first destructive database operation.
            // If backup creation fails, restore is stopped and live data remains untouched.
            $safetyBackup = $this->writeBackupArchive('pre-restore-safety');
            Log::info('Safety backup created before restore.', ['backup' => $safetyBackup]);
            $backupTables = array_keys($payload['tables']);
            sort($backupTables);
            $missingTables = array_values(array_diff($currentTables, $backupTables));
            if (isset($payload['schema_fingerprint'])) {
                $currentSchema = [];
                foreach ($currentTables as $table) {
                    $currentSchema[$table] = Schema::getColumnListing($table);
                }
                $fingerprint = hash('sha256', json_encode($currentSchema, JSON_THROW_ON_ERROR));
                abort_unless(hash_equals($fingerprint, (string) $payload['schema_fingerprint']), 422, 'Backup schema does not match this installed LMS version. Restore was stopped to protect current data.');
            }
            abort_unless($missingTables === [], 422, 'This backup does not contain every table required by the installed LMS version. Update or downgrade the application before restoring.');

            $mediaToRestore = [];
            foreach (($payload['public_media'] ?? []) as $mediaPath => $encoded) {
                abort_unless(is_string($mediaPath) && str_starts_with($mediaPath, 'media/') && ! Str::contains($mediaPath, ['..', '\\']), 422, 'Unsafe media path in backup.');
                $contents = base64_decode($encoded, true);
                abort_unless(is_string($contents), 422, 'A media file in this backup is damaged.');
                $mediaToRestore[$mediaPath] = $contents;
            }

            $privateFilesToRestore = [];
            foreach (($payload['private_files'] ?? []) as $privatePath => $encoded) {
                abort_unless(is_string($privatePath) && str_starts_with($privatePath, 'assignment-submissions/') && ! Str::contains($privatePath, ['..', '\\']), 422, 'Unsafe private file path in backup.');
                $contents = base64_decode($encoded, true);
                abort_unless(is_string($contents), 422, 'A private file in this backup is damaged.');
                $privateFilesToRestore[$privatePath] = $contents;
            }

            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($currentTables, $payload): void {
                    foreach (array_reverse($currentTables) as $table) {
                        DB::table($table)->delete();
                    }
                    foreach ($currentTables as $table) {
                        $rows = $payload['tables'][$table] ?? [];
                        foreach (array_chunk($rows, 500) as $chunk) {
                            if ($chunk !== []) {
                                DB::table($table)->insert($chunk);
                            }
                        }
                    }
                });
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            foreach ($mediaToRestore as $mediaPath => $contents) {
                Storage::disk('public')->put($mediaPath, $contents);
            }
            foreach ($privateFilesToRestore as $privatePath => $contents) {
                Storage::disk('local')->put($privatePath, $contents);
            }
        } catch (Throwable $exception) {
            report($exception);
            abort(422, 'Restore failed. No success was reported; check the application log and database state before retrying.');
        }

        return redirect()->route('admin.backups.index')->with('success', 'Backup restored. Sign out and sign in again to refresh your session.');
    }
}
