<?php

namespace App\Services\Plugins;

use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use RuntimeException;

final class PluginContext
{
    private string $vendor;
    private string $slug;

    public function __construct(
        private readonly string $pluginKey,
        private readonly string $pluginDirectory,
    ) {
        $parts = explode('.', $pluginKey, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Plugin keys must contain vendor and plugin segments.');
        }

        [$this->vendor, $pluginSlug] = $parts;
        $this->slug = str_replace('.', '-', $pluginSlug);
    }

    public function webRoutes(string $relativePath = 'routes/web.php'): void
    {
        $file = $this->resolveRouteFile($relativePath);

        Route::middleware(['web', 'auth', 'verified', 'plugin.active:'.$this->pluginKey])
            ->prefix('plugins/'.$this->vendor.'/'.$this->slug)
            ->name('plugins.'.$this->vendor.'.'.$this->slug.'.')
            ->group($file);
    }

    public function apiRoutes(string $relativePath = 'routes/api.php'): void
    {
        $file = $this->resolveRouteFile($relativePath);

        Route::middleware(['api', 'api.token', 'plugin.active:'.$this->pluginKey])
            ->prefix('api/plugins/'.$this->vendor.'/'.$this->slug)
            ->name('api.plugins.'.$this->vendor.'.'.$this->slug.'.')
            ->group($file);
    }

    private function resolveRouteFile(string $relativePath): string
    {
        if (! str_ends_with(strtolower($relativePath), '.php')) {
            throw new InvalidArgumentException('Plugin route files must use the PHP extension.');
        }

        if ($relativePath === '' || strlen($relativePath) > 240
            || str_starts_with($relativePath, '/')
            || str_contains($relativePath, '\\')
            || str_contains($relativePath, ':')
            || str_contains($relativePath, "\0")) {
            throw new InvalidArgumentException('Invalid plugin route file path.');
        }

        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || preg_match('/^[A-Za-z0-9_.@+-]{1,120}$/D', $segment) !== 1) {
                throw new InvalidArgumentException('Unsafe plugin route file path.');
            }
        }

        if (is_link($this->pluginDirectory) || is_link($this->pluginDirectory.'/'.$relativePath)) {
            throw new RuntimeException('Plugin route files cannot be symbolic links.');
        }

        $root = realpath($this->pluginDirectory);
        $file = realpath($this->pluginDirectory.'/'.$relativePath);
        if ($root === false || $file === false || ! is_file($file)
            || ! str_starts_with($file, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The requested plugin route file does not exist inside the package.');
        }

        return $file;
    }
}
