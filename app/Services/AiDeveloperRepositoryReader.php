<?php

namespace App\\Services;

use Illuminate\\Support\\Facades\\Http;
use RuntimeException;

class AiDeveloperRepositoryReader
{
    private const API_BASE = 'https://api.github.com';

    /**
     * Read only explicitly allow-listed source files. This service never writes to GitHub.
     *
     * @return array<string, string>
     */
    public function readConfiguredFiles(): array
    {
        if (!config('services.ai_developer.repository_context_enabled', false)) {
            return [];
        }

        $token = (string) config('services.ai_developer.github_token', '');
        $repository = (string) config('services.ai_developer.github_repository', '');
        $ref = (string) config('services.ai_developer.github_read_ref', 'main');
        $paths = config('services.ai_developer.allowed_repository_paths', []);

        if ($token === '' || !preg_match('/^[A-Za-z0-9_.-]+\\/[A-Za-z0-9_.-]+$/', $repository)) {
            throw new RuntimeException('Repository reader is not configured.');
        }

        if (!is_array($paths) || $paths === [] || !$this->validRef($ref)) {
            throw new RuntimeException('Repository reader requires a valid read ref and an explicit file allow-list.');
        }

        $maxFileBytes = max(1024, (int) config('services.ai_developer.repository_max_file_bytes', 12000));
        $maxTotalBytes = max($maxFileBytes, (int) config('services.ai_developer.repository_max_total_bytes', 40000));
        $files = [];
        $totalBytes = 0;

        foreach (array_slice(array_values(array_unique($paths)), 0, 8) as $path) {
            if (!is_string($path) || !$this->validPath($path)) {
                throw new RuntimeException('Repository allow-list contains an unsafe path.');
            }

            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
            $response = Http::withToken($token)
                ->accept('application/vnd.github+json')
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(15)
                ->withOptions(['allow_redirects' => false])
                ->get(self::API_BASE.'/repos/'.$repository.'/contents/'.$encodedPath, ['ref' => $ref]);

            if (!$response->successful()) {
                throw new RuntimeException('An allow-listed repository file could not be read.');
            }

            $payload = $response->json();
            if (!is_array($payload) || ($payload['type'] ?? null) !== 'file' || ($payload['encoding'] ?? null) !== 'base64') {
                throw new RuntimeException('GitHub returned an unsupported repository content response.');
            }

            $decoded = base64_decode((string) ($payload['content'] ?? ''), true);
            if (!is_string($decoded) || strlen($decoded) > $maxFileBytes) {
                throw new RuntimeException('An allow-listed repository file exceeded the configured size limit.');
            }

            $totalBytes += strlen($decoded);
            if ($totalBytes > $maxTotalBytes) {
                throw new RuntimeException('Repository context exceeded the configured total size limit.');
            }

            $files[$path] = $this->redact($decoded);
        }

        return $files;
    }

    private function validRef(string $ref): bool
    {
        return $ref !== ''
            && strlen($ref) <= 100
            && preg_match('/^[A-Za-z0-9._\\/-]+$/', $ref) === 1
            && !str_contains($ref, '..')
            && !str_contains($ref, '//')
            && !str_starts_with($ref, '/');
    }

    private function validPath(string $path): bool
    {
        if ($path === '' || strlen($path) > 180 || str_starts_with($path, '/') || str_contains($path, '\\\\')) {
            return false;
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        $lower = strtolower($path);
        foreach (['.env', '.git/', 'vendor/', 'node_modules/', 'storage/', 'bootstrap/cache/', 'private', 'secret'] as $blocked) {
            if (str_contains($lower, $blocked)) {
                return false;
            }
        }

        return preg_match('/\\.(php|json|ya?ml|md)$/i', $path) === 1;
    }

    private function redact(string $content): string
    {
        $content = preg_replace('/-----BEGIN [A-Z ]*PRIVATE KEY-----[\\s\\S]*?-----END [A-Z ]*PRIVATE KEY-----/', '[REDACTED_PRIVATE_KEY]', $content) ?? $content;
        $content = preg_replace('/(Bearer\\s+)[A-Za-z0-9._-]+/i', '$1[REDACTED]', $content) ?? $content;
        $content = preg_replace('/\\b(sk-[A-Za-z0-9_-]{16,}|gh[pousr]_[A-Za-z0-9]{20,}|xox[baprs]-[A-Za-z0-9-]{10,}|AKIA[0-9A-Z]{16})\\b/', '[REDACTED_SECRET]', $content) ?? $content;
        $content = preg_replace('/(?im)^([ \\t]*(?:DB_PASSWORD|APP_KEY|AWS_SECRET_ACCESS_KEY|NAGAD_MERCHANT_PRIVATE_KEY)\\s*[=:]\\s*)[^\\r\\n]+/', '$1[REDACTED]', $content) ?? $content;
        $content = preg_replace('/\\b01[3-9]\\d{8}\\b/', '[REDACTED_PHONE]', $content) ?? $content;

        return $content;
    }
}
