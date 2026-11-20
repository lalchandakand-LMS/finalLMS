<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ZipArchive;
use Throwable;

class ThemeManagerController extends Controller
{
    public function index(): View
    {
        $themes = DB::table('themes')->orderBy('name')->orderByDesc('created_at')->get();
        $history = DB::table('theme_activations')
            ->leftJoin('users', 'users.id', '=', 'theme_activations.actor_id')
            ->leftJoin('themes', 'themes.id', '=', 'theme_activations.theme_id')
            ->orderByDesc('theme_activations.id')
            ->limit(50)
            ->get([
                'theme_activations.id',
                'theme_activations.action',
                'theme_activations.previous_state',
                'theme_activations.created_at',
                'users.name as actor_name',
                'themes.name as theme_name',
            ]);
        $activeThemeId = (int) Setting::value('active_theme_id', 0);

        return view('admin.themes.index', [
            'themes' => $themes,
            'history' => $history,
            'activeThemeId' => $activeThemeId,
            'signingConfigured' => filled(config('themes.public_key')),
        ]);
    }

    public function install(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'package' => ['required', 'file', 'max:'.(int) config('themes.max_package_kb', 10240), 'extensions:zip'],
        ]);

        $publicKey = config('themes.public_key');
        if (! is_string($publicKey) || trim($publicKey) === '') {
            return back()->withErrors(['package' => __('Theme installation is disabled until the institution signing public key is configured.')]);
        }

        if (! class_exists(ZipArchive::class)) {
            return back()->withErrors(['package' => __('The PHP ZIP extension is required to install themes.')]);
        }

        $publicKey = str_replace('\\n', "\n", $publicKey);
        $zip = new ZipArchive();
        if ($zip->open($validated['package']->getRealPath()) !== true) {
            return back()->withErrors(['package' => __('The uploaded file is not a valid ZIP archive.')]);
        }

        $writtenPaths = [];
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > 30) {
                throw new \RuntimeException(__('The theme archive contains an invalid number of files.'));
            }

            // Inspect the complete central directory before decompressing any entry.
            // This prevents oversized compressed entries from being read into memory first.
            $seenNames = [];
            $declaredExpandedBytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'] ?? '';
                $size = (int) ($stat['size'] ?? -1);

                if ($name === '' || isset($seenNames[$name])) {
                    throw new \RuntimeException(__('Duplicate or invalid archive paths are not allowed.'));
                }
                $seenNames[$name] = true;

                $allowedName = in_array($name, ['theme.json', 'theme.sig', 'preview.png', 'assets/'], true)
                    || preg_match('#^assets/[a-z0-9][a-z0-9._-]{0,100}\.css$#i', $name);
                if (! $allowedName || $size < 0) {
                    throw new \RuntimeException(__('The archive contains an unexpected file or directory.'));
                }

                $entryLimit = match ($name) {
                    'theme.json' => 65536,
                    'theme.sig' => 4096,
                    'preview.png' => 1048576,
                    'assets/' => 0,
                    default => 262144,
                };
                if ($size > $entryLimit) {
                    throw new \RuntimeException(__('A theme archive entry exceeds its allowed uncompressed size.'));
                }

                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
                    && $opsys === ZipArchive::OPSYS_UNIX
                    && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new \RuntimeException(__('Symbolic links are not allowed in theme packages.'));
                }

                $declaredExpandedBytes += $size;
                if ($declaredExpandedBytes > (int) config('themes.max_expanded_kb', 2048) * 1024) {
                    throw new \RuntimeException(__('The expanded theme exceeds the allowed size.'));
                }
            }

            if (! isset($seenNames['theme.json'], $seenNames['theme.sig'])) {
                throw new \RuntimeException(__('The archive must contain theme.json and theme.sig.'));
            }

            $manifestBytes = $this->readZipEntryBounded($zip, 'theme.json', 65536);
            $signatureBytes = $this->readZipEntryBounded($zip, 'theme.sig', 4096);
            if (! is_string($manifestBytes) || ! is_string($signatureBytes) || strlen($manifestBytes) > 65536 || strlen($signatureBytes) > 4096) {
                throw new \RuntimeException(__('The archive must contain theme.json and theme.sig.'));
            }

            $signature = base64_decode(trim($signatureBytes), true);
            $key = openssl_pkey_get_public($publicKey);
            if ($signature === false || $key === false || openssl_verify($manifestBytes, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
                throw new \RuntimeException(__('Theme signature verification failed. The package is not trusted.'));
            }

            $manifest = json_decode($manifestBytes, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($manifest)
                || ($manifest['product_id'] ?? null) !== 'aloms-lms-theme'
                || ! is_string($manifest['slug'] ?? null)
                || ! preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/', $manifest['slug'])
                || ! is_string($manifest['version'] ?? null)
                || ! preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/', $manifest['version'])
                || ! is_string($manifest['name'] ?? null)
                || trim($manifest['name']) === ''
                || mb_strlen($manifest['name']) > 120
                || ! is_array($manifest['assets'] ?? null)
                || count($manifest['assets']) < 1
                || count($manifest['assets']) > (int) config('themes.max_assets', 8)) {
                throw new \RuntimeException(__('The theme manifest is invalid or incompatible with Alom LMS.'));
            }

            $minVersion = (string) ($manifest['min_lms_version'] ?? '1.0.0');
            if (! preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/', $minVersion)) {
                throw new \RuntimeException(__('The theme minimum LMS version is invalid.'));
            }
            $maxVersion = $manifest['max_lms_version'] ?? null;
            if ($maxVersion !== null && (! is_string($maxVersion) || ! preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/', $maxVersion))) {
                throw new \RuntimeException(__('The theme maximum LMS version is invalid.'));
            }
            if (is_string($maxVersion) && version_compare($minVersion, $maxVersion, '>')) {
                throw new \RuntimeException(__('The theme LMS compatibility range is invalid.'));
            }
            $currentVersion = (string) config('app.version', '1.0.0');
            if (version_compare($currentVersion, $minVersion, '<')
                || (is_string($maxVersion) && version_compare($currentVersion, $maxVersion, '>'))) {
                throw new \RuntimeException(__('This theme is not compatible with the current LMS version.'));
            }

            $assetContents = [];
            $expandedBytes = 0;
            $expectedNames = ['theme.json' => true, 'theme.sig' => true];
            foreach ($manifest['assets'] as $assetName => $digest) {
                if (! is_string($assetName)
                    || ! preg_match('/^assets\/[a-z0-9][a-z0-9._-]{0,100}\.css$/i', $assetName)
                    || str_contains($assetName, '..')
                    || ! is_string($digest)
                    || ! preg_match('/^[a-f0-9]{64}$/i', $digest)) {
                    throw new \RuntimeException(__('The theme contains an invalid asset declaration.'));
                }
                $expectedNames[$assetName] = true;
                $contents = $this->readZipEntryBounded($zip, $assetName, 262144);
                if (! is_string($contents) || strlen($contents) > 262144 || ! hash_equals(strtolower($digest), hash('sha256', $contents))) {
                    throw new \RuntimeException(__('A theme asset is missing, too large or has an invalid checksum.'));
                }
                if (! mb_check_encoding($contents, 'UTF-8')
                    || preg_match('/@import\b|url\s*\(|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding|<\s*\/?\s*style/i', $contents)) {
                    throw new \RuntimeException(__('Theme CSS contains a prohibited external reference or executable construct.'));
                }
                $expandedBytes += strlen($contents);
                $assetContents[$assetName] = $contents;
            }

            $previewBytes = $this->readZipEntryBounded($zip, 'preview.png', 1048576);
            if (is_string($previewBytes)) {
                if (! is_string($manifest['preview_sha256'] ?? null) || ! hash_equals(strtolower($manifest['preview_sha256']), hash('sha256', $previewBytes))) {
                    throw new \RuntimeException(__('The theme preview checksum is missing or invalid.'));
                }
                $expectedNames['preview.png'] = true;
                $image = @getimagesizefromstring($previewBytes);
                if ($image === false || ($image['mime'] ?? '') !== 'image/png' || strlen($previewBytes) > 1048576) {
                    throw new \RuntimeException(__('The theme preview must be a PNG image smaller than 1 MB.'));
                }
                $expandedBytes += strlen($previewBytes);
            }

            $seenNames = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'] ?? '';
                if (isset($seenNames[$name])) {
                    throw new \RuntimeException(__('Duplicate archive paths are not allowed.'));
                }
                $seenNames[$name] = true;
                if ($name === 'assets/') {
                    continue;
                }
                if (! isset($expectedNames[$name])) {
                    throw new \RuntimeException(__('The archive contains an unexpected file or directory.'));
                }
                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
                    && $opsys === ZipArchive::OPSYS_UNIX
                    && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new \RuntimeException(__('Symbolic links are not allowed in theme packages.'));
                }
                if (($stat['size'] ?? 0) > 1048576) {
                    throw new \RuntimeException(__('A theme archive entry exceeds the allowed size.'));
                }
            }

            if ($expandedBytes > (int) config('themes.max_expanded_kb', 2048) * 1024) {
                throw new \RuntimeException(__('The expanded theme exceeds the allowed size.'));
            }

            $slug = $manifest['slug'];
            $version = $manifest['version'];

            DB::transaction(function () use (
                $slug, $version, $assetContents, $previewBytes, $validated, $manifest,
                $minVersion, $request, &$writtenPaths
            ): void {
                // Serialize installation with activate/reset/delete so two uploads
                // for the same slug/version cannot overwrite shared asset paths.
                $lock = DB::table('theme_manager_locks')->where('id', 1)->lockForUpdate()->first();
                if (! $lock) {
                    throw new \RuntimeException(__('Theme installation is unavailable because the lifecycle lock is missing.'));
                }
                if (DB::table('themes')->where('slug', $slug)->where('version', $version)->exists()) {
                    throw new \RuntimeException(__('This theme version is already installed.'));
                }

                $base = 'themes/'.$slug.'/'.$version;
                $publicDisk = Storage::disk('public');
                $previewPath = is_string($previewBytes) ? $base.'/preview.png' : null;

                // A failed prior write/cleanup may leave files without a DB row.
                // Never overwrite such paths: the earlier request may still be
                // cleaning them after its transaction released the lifecycle lock.
                foreach (array_keys($assetContents) as $assetName) {
                    if ($publicDisk->exists($base.'/'.$assetName)) {
                        throw new \RuntimeException(__('Theme storage paths already exist without a registry entry; installation was blocked to protect existing files.'));
                    }
                }
                if ($previewPath !== null && $publicDisk->exists($previewPath)) {
                    throw new \RuntimeException(__('Theme storage paths already exist without a registry entry; installation was blocked to protect existing files.'));
                }

                foreach ($assetContents as $assetName => $contents) {
                    $path = $base.'/'.$assetName;
                    $writtenPaths[] = $path;
                    if (! $publicDisk->put($path, $contents)) {
                        throw new \RuntimeException(__('A theme asset could not be written to storage.'));
                    }
                }

                if ($previewPath !== null) {
                    $writtenPaths[] = $previewPath;
                    if (! $publicDisk->put($previewPath, $previewBytes)) {
                        throw new \RuntimeException(__('The theme preview could not be written to storage.'));
                    }
                }

                $packagePath = $validated['package']->storeAs('themes/packages', Str::uuid().'.zip', 'local');
                if (! is_string($packagePath) || $packagePath === '') {
                    throw new \RuntimeException(__('The original theme package could not be retained on private storage.'));
                }
                $writtenPaths[] = 'local:'.$packagePath;
                $packageSha256 = hash_file('sha256', $validated['package']->getRealPath());
                if (! is_string($packageSha256)) {
                    throw new \RuntimeException(__('The original theme package checksum could not be calculated.'));
                }

                DB::table('themes')->insert([
                    'slug' => $slug,
                    'version' => $version,
                    'name' => trim($manifest['name']),
                    'author' => is_string($manifest['author'] ?? null) ? mb_substr($manifest['author'], 0, 120) : null,
                    'description' => is_string($manifest['description'] ?? null) ? mb_substr($manifest['description'], 0, 500) : null,
                    'min_lms_version' => $minVersion,
                    'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
                    'assets' => json_encode(array_keys($assetContents), JSON_THROW_ON_ERROR),
                    'preview_path' => $previewPath,
                    'package_path' => $packagePath,
                    'package_sha256' => $packageSha256,
                    'installed_by' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            return redirect()->route('admin.themes.index')->with('success', __('Theme package verified and installed. It has not been activated.'));
        } catch (Throwable $exception) {
            foreach ($writtenPaths as $path) {
                if (str_starts_with($path, 'local:')) {
                    Storage::disk('local')->delete(substr($path, 6));
                } else {
                    Storage::disk('public')->delete($path);
                }
            }

            report($exception);
            return back()->withErrors(['package' => $exception instanceof \RuntimeException ? $exception->getMessage() : __('The theme package could not be installed safely.')]);
        } finally {
            $zip->close();
        }
    }

    public function preview(int $theme): View
    {
        $record = DB::table('themes')->where('id', $theme)->first();
        abort_unless($record, 404);
        try {
            $this->assertThemeCanActivate($record);
        } catch (\RuntimeException) {
            abort(404);
        }

        return view('admin.themes.preview', ['theme' => $record]);
    }

    public function previewFrame(int $theme)
    {
        $record = DB::table('themes')->where('id', $theme)->first();
        abort_unless($record, 404);
        try {
            $this->assertThemeCanActivate($record);
        } catch (\RuntimeException) {
            abort(404);
        }

        $css = '';
        foreach (json_decode($record->assets, true) ?: [] as $asset) {
            if (is_string($asset) && preg_match('/^assets\/[a-z0-9][a-z0-9._-]{0,100}\.css$/i', $asset)) {
                $css .= Storage::disk('public')->get('themes/'.$record->slug.'/'.$record->version.'/'.$asset)."\n";
            }
        }

        return response()
            ->view('admin.themes.preview-frame', ['theme' => $record, 'css' => $css])
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src 'none'; script-src 'none'; connect-src 'none'; form-action 'none'; base-uri 'none'; frame-ancestors 'self'")
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function destroy(Request $request, int $theme): RedirectResponse
    {
        $paths = [];
        $packagePath = null;

        try {
            DB::transaction(function () use ($request, $theme, &$paths, &$packagePath): void {
                $lock = DB::table('theme_manager_locks')->where('id', 1)->lockForUpdate()->first();
                if (! $lock) {
                    throw new \RuntimeException(__('Theme deletion is unavailable because the lifecycle lock is missing.'));
                }

                $record = DB::table('themes')->where('id', $theme)->lockForUpdate()->first();
                abort_unless($record, 404);

                $activeId = (int) Setting::value('active_theme_id', 0);
                $activeSlug = (string) Setting::value('active_theme_slug', '');
                $activeVersion = (string) Setting::value('active_theme_version', '');
                if ($activeId === (int) $record->id
                    || ($activeSlug !== '' && $activeSlug === $record->slug && $activeVersion === $record->version)) {
                    throw new \RuntimeException(__('The active theme cannot be deleted. Activate another theme or reset to the built-in theme first.'));
                }

                if (! preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/', (string) $record->slug)
                    || ! preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/', (string) $record->version)) {
                    throw new \RuntimeException(__('The installed theme has invalid storage metadata; deletion was blocked.'));
                }

                $base = 'themes/'.$record->slug.'/'.$record->version;
                $assets = json_decode((string) $record->assets, true);
                if (! is_array($assets)) {
                    throw new \RuntimeException(__('The installed theme asset list is invalid; deletion was blocked.'));
                }
                foreach ($assets as $asset) {
                    if (! is_string($asset) || ! preg_match('/^assets\/[a-z0-9][a-z0-9._-]{0,100}\.css$/i', $asset) || str_contains($asset, '..')) {
                        throw new \RuntimeException(__('An unsafe theme asset path was found; deletion was blocked.'));
                    }
                    $paths[] = ['public', $base.'/'.$asset];
                }

                if ($record->preview_path !== null) {
                    if ($record->preview_path !== $base.'/preview.png') {
                        throw new \RuntimeException(__('The theme preview path is outside its package directory; deletion was blocked.'));
                    }
                    $paths[] = ['public', $record->preview_path];
                }

                $packagePath = (string) $record->package_path;
                if (! preg_match('#^themes/packages/[a-f0-9-]{36}\.zip$#i', $packagePath)) {
                    throw new \RuntimeException(__('The original theme package path is invalid; deletion was blocked.'));
                }

                foreach ($paths as [$diskName, $relativePath]) {
                    $this->assertSafeThemeStoragePath($diskName, $relativePath);
                }
                $this->assertSafeThemeStoragePath('local', $packagePath);

                DB::table('theme_activations')->insert([
                    'theme_id' => $record->id,
                    'action' => 'delete',
                    'previous_state' => json_encode([
                        'id' => (int) $record->id,
                        'slug' => $record->slug,
                        'version' => $record->version,
                        'name' => $record->name,
                        'package_sha256' => $record->package_sha256,
                    ], JSON_THROW_ON_ERROR),
                    'actor_id' => $request->user()->id,
                    'created_at' => now(),
                ]);

                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'event' => 'theme.deleted',
                    'description' => 'Deleted theme '.$record->slug.'@'.$record->version.' (id '.$record->id.')',
                    'method' => 'DELETE',
                    'route' => substr($request->route()?->getName() ?? 'admin.themes.destroy', 0, 190),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]);

                // Keep the database record until every file has been removed. If
                // storage cleanup fails, the transaction rolls back and Admin can
                // retry deletion instead of losing the only paths to orphaned files.
                foreach ($paths as [$diskName, $relativePath]) {
                    $disk = Storage::disk($diskName);
                    if ($disk->exists($relativePath) && ! $disk->delete($relativePath)) {
                        throw new \RuntimeException(__('Theme file cleanup failed; the theme record was retained for retry.'));
                    }
                }
                $localDisk = Storage::disk('local');
                if ($localDisk->exists($packagePath) && ! $localDisk->delete($packagePath)) {
                    throw new \RuntimeException(__('Theme package cleanup failed; the theme record was retained for retry.'));
                }

                DB::table('themes')->where('id', $record->id)->delete();
            }, 3);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['theme' => $exception->getMessage()]);
        }

        return redirect()->route('admin.themes.index')->with('success', __('The inactive theme and its verified package files were deleted. The audit history and website content were retained.'));
    }

    private function readZipEntryBounded(ZipArchive $zip, string $name, int $maxBytes): ?string
    {
        $stream = $zip->getStream($name);
        if ($stream === false) {
            return null;
        }

        $contents = '';
        try {
            while (! feof($stream)) {
                $remaining = ($maxBytes + 1) - strlen($contents);
                if ($remaining <= 0) {
                    throw new \RuntimeException(__('A theme archive entry exceeds its allowed uncompressed size.'));
                }

                $chunk = fread($stream, min(8192, $remaining));
                if ($chunk === false) {
                    throw new \RuntimeException(__('A theme archive entry could not be safely read.'));
                }
                if ($chunk === '') {
                    break;
                }

                $contents .= $chunk;
                if (strlen($contents) > $maxBytes) {
                    throw new \RuntimeException(__('A theme archive entry exceeds its allowed uncompressed size.'));
                }
            }
        } finally {
            fclose($stream);
        }

        return $contents;
    }

    private function assertSafeThemeStoragePath(string $diskName, string $relativePath): void
    {
        if ($relativePath === '' || str_starts_with($relativePath, '/') || str_contains($relativePath, '\\')
            || in_array('..', explode('/', $relativePath), true)) {
            throw new \RuntimeException(__('An unsafe theme storage path was detected.'));
        }

        $disk = Storage::disk($diskName);
        $root = realpath($disk->path(''));
        if ($root === false) {
            throw new \RuntimeException(__('Theme storage is unavailable; deletion was blocked.'));
        }

        $cursor = rtrim($disk->path(''), DIRECTORY_SEPARATOR);
        foreach (explode('/', $relativePath) as $segment) {
            $cursor .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($cursor)) {
                throw new \RuntimeException(__('A symbolic link was found in theme storage; deletion was blocked.'));
            }
            if (file_exists($cursor)) {
                $real = realpath($cursor);
                if ($real === false || ($real !== $root && ! str_starts_with($real, $root.DIRECTORY_SEPARATOR))) {
                    throw new \RuntimeException(__('A theme storage path resolves outside its storage directory; deletion was blocked.'));
                }
            }
        }
    }

    public function activate(Request $request, int $theme): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $theme): void {
                $lock = DB::table('theme_manager_locks')->where('id', 1)->lockForUpdate()->first();
                if (! $lock) {
                    throw new \RuntimeException(__('Theme activation is unavailable because the lifecycle lock is missing.'));
                }

                $record = DB::table('themes')->where('id', $theme)->lockForUpdate()->first();
                abort_unless($record, 404);
                $this->assertThemeCanActivate($record);

                $previous = [
                    'id' => (int) Setting::value('active_theme_id', 0),
                    'slug' => Setting::value('active_theme_slug'),
                    'version' => Setting::value('active_theme_version'),
                ];
                DB::table('theme_activations')->insert([
                    'theme_id' => $record->id,
                    'action' => 'activate',
                    'previous_state' => json_encode($previous, JSON_THROW_ON_ERROR),
                    'actor_id' => $request->user()->id,
                    'created_at' => now(),
                ]);
                Setting::setValue('active_theme_id', $record->id);
                Setting::setValue('active_theme_slug', $record->slug);
                Setting::setValue('active_theme_version', $record->version);
            }, 3);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['theme' => $exception->getMessage()]);
        }

        return redirect()->route('admin.themes.index')->with('success', __('Theme activated. The previous theme is retained in activation history.'));
    }

    public function reset(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $lock = DB::table('theme_manager_locks')->where('id', 1)->lockForUpdate()->first();
            if (! $lock) {
                throw new \RuntimeException('Theme lifecycle lock is missing.');
            }

            $previous = [
                'id' => (int) Setting::value('active_theme_id', 0),
                'slug' => Setting::value('active_theme_slug'),
                'version' => Setting::value('active_theme_version'),
            ];
            // A reset activates the built-in theme, which has no themes-table row.
            // The previous installed theme remains available in previous_state for rollback/audit.
            DB::table('theme_activations')->insert([
                'theme_id' => null,
                'action' => 'reset',
                'previous_state' => json_encode($previous, JSON_THROW_ON_ERROR),
                'actor_id' => $request->user()->id,
                'created_at' => now(),
            ]);
            Setting::setValue('active_theme_id', 0);
            Setting::setValue('active_theme_slug', '');
            Setting::setValue('active_theme_version', '');
        }, 3);

        return redirect()->route('admin.themes.index')->with('success', __('The built-in Alom LMS theme has been restored. Your pages, menus, media and course data were not changed.'));
    }

    private function assertThemeCanActivate(object $theme): void
    {
        $manifest = json_decode((string) $theme->manifest, true);
        $assets = json_decode((string) $theme->assets, true);
        if (! is_array($manifest)
            || ($manifest['product_id'] ?? null) !== 'aloms-lms-theme'
            || ! is_array($assets)
            || ! is_array($manifest['assets'] ?? null)) {
            throw new \RuntimeException(__('The installed theme manifest is invalid.'));
        }

        $currentVersion = (string) config('app.version', '1.0.0');
        $minimum = (string) ($theme->min_lms_version ?: '1.0.0');
        $maximum = $manifest['max_lms_version'] ?? null;
        $versionPattern = '/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/';
        if (! preg_match($versionPattern, $minimum)
            || ($maximum !== null && (! is_string($maximum) || ! preg_match($versionPattern, $maximum)))
            || ($maximum !== null && version_compare($minimum, $maximum, '>'))) {
            throw new \RuntimeException(__('The installed theme has invalid LMS compatibility metadata.'));
        }
        if (version_compare($currentVersion, $minimum, '<')
            || (is_string($maximum) && version_compare($currentVersion, $maximum, '>'))) {
            throw new \RuntimeException(__('This theme is not compatible with the current LMS version.'));
        }

        foreach ($assets as $asset) {
            if (! is_string($asset)
                || ! preg_match('/^assets\/[a-z0-9][a-z0-9._-]{0,100}\.css$/i', $asset)
                || str_contains($asset, '..')) {
                throw new \RuntimeException(__('The installed theme contains an invalid asset path.'));
            }

            $digest = $manifest['assets'][$asset] ?? null;
            $path = 'themes/'.$theme->slug.'/'.$theme->version.'/'.$asset;
            if (! is_string($digest)
                || ! preg_match('/^[a-f0-9]{64}$/i', $digest)
                || ! Storage::disk('public')->exists($path)) {
                throw new \RuntimeException(__('A required theme asset is missing or has an invalid checksum.'));
            }

            $contents = Storage::disk('public')->get($path);
            if (! hash_equals(strtolower($digest), hash('sha256', $contents))) {
                throw new \RuntimeException(__('A theme asset changed after installation; activation was blocked.'));
            }
        }
    }

    public function asset(string $slug, string $version, string $file)
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/', $slug)
            && preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/', $version)
            && preg_match('/^[a-z0-9][a-z0-9._-]{0,100}\.css$/i', $file), 404);

        $theme = DB::table('themes')->where('slug', $slug)->where('version', $version)->first();
        abort_unless($theme, 404);
        $assets = json_decode($theme->assets, true) ?: [];
        $asset = 'assets/'.$file;
        $path = 'themes/'.$slug.'/'.$version.'/'.$asset;
        $manifest = json_decode((string) $theme->manifest, true);
        $digest = is_array($manifest) ? ($manifest['assets'][$asset] ?? null) : null;
        abort_unless(in_array($asset, $assets, true)
            && is_string($digest)
            && preg_match('/^[a-f0-9]{64}$/i', $digest)
            && Storage::disk('public')->exists($path), 404);

        $contents = Storage::disk('public')->get($path);
        abort_unless(hash_equals(strtolower($digest), hash('sha256', $contents)), 404);

        return response($contents, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
