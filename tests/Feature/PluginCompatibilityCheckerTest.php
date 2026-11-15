<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginCompatibilityChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PluginCompatibilityCheckerTest extends TestCase
{
    use RefreshDatabase;

    private function manifest(array $overrides = []): array
    {
        return array_replace_recursive([
            'key' => 'aec.course-management',
            'requires' => [
                'php' => '^8.3',
                'laravel' => '^12.0',
                'lms' => '^1.0',
            ],
            'dependencies' => [],
            'conflicts' => [],
        ], $overrides);
    }

    private function register(string $key, string $status = 'installed'): void
    {
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => $key,
            'name' => $key,
            'version' => '1.0.0',
            'type' => 'module',
            'status' => $status,
            'manifest' => ['key' => $key],
        ])->save();
    }

    public function test_accepts_current_php_laravel_and_lms_compatibility_ranges(): void
    {
        $this->assertSame([], app(PluginCompatibilityChecker::class)->errors($this->manifest()));
    }

    public function test_rejects_incompatible_php_laravel_and_lms_versions(): void
    {
        $errors = app(PluginCompatibilityChecker::class)->errors($this->manifest([
            'requires' => [
                'php' => '^7.4',
                'laravel' => '^10.0',
                'lms' => '^2.0',
            ],
        ]));

        $this->assertCount(3, $errors);
    }

    public function test_requires_declared_dependencies_to_be_installed(): void
    {
        $errors = app(PluginCompatibilityChecker::class)->errors($this->manifest([
            'dependencies' => ['aec.identity', 'aec.media-library'],
        ]));

        $this->assertStringContainsString('aec.identity', implode(' ', $errors));
        $this->register('aec.identity', 'inactive');
        $this->assertSame([], app(PluginCompatibilityChecker::class)->errors($this->manifest([
            'dependencies' => ['aec.identity'],
        ])));
    }

    public function test_rejects_conflicts_with_registered_plugins(): void
    {
        $this->register('aec.legacy-courses');

        $errors = app(PluginCompatibilityChecker::class)->errors($this->manifest([
            'conflicts' => ['aec.legacy-courses'],
        ]));

        $this->assertStringContainsString('aec.legacy-courses', implode(' ', $errors));
    }

    public function test_rejects_unsupported_version_constraint_syntax(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(PluginCompatibilityChecker::class)->errors($this->manifest([
            'requires' => ['php' => '^8.3 || ^8.4'],
        ]));
    }
}
