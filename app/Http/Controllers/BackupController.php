<?php

namespace App\Http\Controllers;

use App\Models\SystemUpdateEvent;
use App\Services\UpdateOperationLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class BackupController extends Controller
{
    private const IGNORED_TABLES = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'system_update_locks', 'system_update_events'];


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

    public function create(UpdateOperationLock $operationLock): RedirectResponse
    {
        try {
            $filename = $operationLock->run('manual_backup', auth()->user(), function (): string {
                        $tableNames = $this->backupTableNames();
                        sort($tableNames);
                        $tables = [];
                        foreach ($tableNames as $table) {
                            $tables[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
                        }
                
                        // Back up every file on the public upload disk, not only Media Library
                        // records. Course images and lesson thumbnails are stored separately.
                        $mediaFiles = [];
                        foreach (Storage::disk('public')->allFiles() as $path) {
                            $mediaFiles[$path] = base64_encode(Storage::disk('public')->get($path));
                        }
                
                        // Preserve media assets stored on an explicitly configured disk too.
                        if (in_array('media_assets', $tableNames, true)) {
                            foreach ($tables['media_assets'] as $asset) {
                                $disk = $asset['disk'] ?? 'public';
                                $path = $asset['path'] ?? '';
                                if ($path !== '' && ! array_key_exists($path, $mediaFiles) && Storage::disk($disk)->exists($path)) {
                                    $mediaFiles[$path] = base64_encode(Storage::disk($disk)->get($path));
                                }
                            }
                        }
                
                        // The local disk is the private user-data root. Include every private
                        // upload (payment proofs, teacher documents, assignment submissions,
                        // lesson attachments and course videos), but never nest backups or
                        // updater staging snapshots inside another backup.
                        $privateFiles = [];
                        foreach (Storage::disk('local')->allFiles() as $privatePath) {
                            if (str_starts_with($privatePath, 'backups/') || str_starts_with($privatePath, 'updates/')) {
                                continue;
                            }
                            $privateFiles[$privatePath] = base64_encode(Storage::disk('local')->get($privatePath));
                        }
                
                        $payload = json_encode([
                            'format_version' => 1,
                            'created_at' => now()->toIso8601String(),
                            'tables' => $tables,
                            'public_media' => $mediaFiles,
                            'private_files' => $privateFiles,
                        ], JSON_THROW_ON_ERROR);
                
                        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));
                        $archive = gzencode(json_encode([
                            'payload' => base64_encode($payload),
                            'signature' => $signature,
                        ], JSON_THROW_ON_ERROR), 9);
                
                        if (! is_string($archive) || $archive === '') {
                            throw new \RuntimeException('The database/media backup archive could not be compressed.');
                        }
                
                        // Publish only a complete, readable and HMAC-verifiable archive. Write to
                        // a private temporary name first so interrupted writes never look like
                        // finished backups in the Admin UI or updater preflight.
                        $disk = Storage::disk('local');
                        $filename = 'aloms-lms-backup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(5)).'.json.gz';
                        $finalPath = 'backups/'.$filename;
                        $temporaryPath = 'backups/.'.$filename.'.'.Str::lower(Str::random(10)).'.tmp';
                        $published = false;
                
                        try {
                            if (! $disk->put($temporaryPath, $archive)) {
                                throw new \RuntimeException('The temporary database/media backup could not be written to private storage.');
                            }
                
                            $storedArchive = $disk->get($temporaryPath);
                            $storedEnvelopeJson = @gzdecode($storedArchive, 536870912);
                            if (! is_string($storedEnvelopeJson)) {
                                throw new \RuntimeException('The written database/media backup failed its compression integrity check.');
                            }
                
                            $storedEnvelope = json_decode($storedEnvelopeJson, true, 512, JSON_THROW_ON_ERROR);
                            $storedPayload = base64_decode($storedEnvelope['payload'] ?? '', true);
                            $storedSignature = $storedEnvelope['signature'] ?? null;
                            $expectedSignature = is_string($storedPayload)
                                ? hash_hmac('sha256', $storedPayload, (string) config('app.key'))
                                : '';
                
                            if (! is_string($storedPayload)
                                || ! is_string($storedSignature)
                                || ! hash_equals(hash('sha256', $payload), hash('sha256', $storedPayload))
                                || ! hash_equals($expectedSignature, $storedSignature)) {
                                throw new \RuntimeException('The written database/media backup failed its payload or HMAC integrity check.');
                            }
                
                            if (! $disk->move($temporaryPath, $finalPath)) {
                                throw new \RuntimeException('The verified database/media backup could not be published.');
                            }
                
                            $publishedBytes = $disk->get($finalPath);
                            if (! hash_equals(hash('sha256', $archive), hash('sha256', $publishedBytes))) {
                                throw new \RuntimeException('The published database/media backup failed its final file integrity check.');
                            }
                
                            $published = true;
                        } finally {
                            if ($disk->exists($temporaryPath)) {
                                $disk->delete($temporaryPath);
                            }
                            if (! $published && $disk->exists($finalPath)) {
                                $disk->delete($finalPath);
                            }
                        }
                
                        return $filename;
            });

            SystemUpdateEvent::create([
                'performed_by' => auth()->user()->id,
                'event' => 'manual_backup',
                'status' => 'success',
                'message' => 'A complete database and media backup was published after integrity verification.',
                'context' => ['filename' => $filename],
            ]);

            return redirect()->route('admin.backups.index')->with('success', 'Database and uploaded media backup created and integrity-verified.');
        } catch (Throwable $exception) {
            report($exception);
            SystemUpdateEvent::create([
                'performed_by' => auth()->user()->id,
                'event' => 'manual_backup',
                'status' => 'failed',
                'message' => 'Manual database/media backup failed; inspect the server log.',
            ]);

            return redirect()->route('admin.backups.index')->with('error', 'The backup could not be completed safely. No incomplete backup was published; review the server log.');
        }
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
            'backup_file' => ['required', 'file', 'max:524288'],
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
            $backupTables = array_keys($payload['tables']);
            sort($backupTables);
            $missingTables = array_values(array_diff($currentTables, $backupTables));
            abort_unless($missingTables === [], 422, 'This backup does not contain every table required by the installed LMS version. Update or downgrade the application before restoring.');

            $mediaToRestore = [];
            foreach (($payload['public_media'] ?? []) as $mediaPath => $encoded) {
                $segments = is_string($mediaPath) ? explode('/', $mediaPath) : [];
                $safePath = is_string($mediaPath)
                    && $mediaPath !== ''
                    && ! str_starts_with($mediaPath, '/')
                    && ! str_contains($mediaPath, '\\')
                    && ! str_contains($mediaPath, "\0")
                    && ! in_array('', $segments, true)
                    && ! in_array('.', $segments, true)
                    && ! in_array('..', $segments, true);
                abort_unless($safePath, 422, 'Unsafe media path in backup.');
                $contents = base64_decode($encoded, true);
                abort_unless(is_string($contents), 422, 'A media file in this backup is damaged.');
                $mediaToRestore[$mediaPath] = $contents;
            }

            $privateFilesToRestore = [];
            foreach (($payload['private_files'] ?? []) as $privatePath => $encoded) {
                $segments = is_string($privatePath) ? explode('/', $privatePath) : [];
                $safePath = is_string($privatePath)
                    && $privatePath !== ''
                    && ! str_starts_with($privatePath, '/')
                    && ! str_contains($privatePath, '\\')
                    && ! str_contains($privatePath, "\0")
                    && ! in_array('', $segments, true)
                    && ! in_array('.', $segments, true)
                    && ! in_array('..', $segments, true)
                    && ! in_array($segments[0] ?? '', ['backups', 'updates'], true);
                abort_unless($safePath, 422, 'Unsafe private file path in backup.');
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
