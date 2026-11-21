<?php

namespace Tests\Unit;

use App\Services\Plugins\PluginManifestValidator;
use Tests\TestCase;

class PluginManifestValidatorTest extends TestCase
{
    private function validManifest(): array
    {
        return [
            'schema_version' => 1,
            'key' => 'aec.course-management',
            'name' => 'Course Management',
            'version' => '1.0.0',
            'type' => 'module',
            'entrypoint' => 'src/Plugin.php',
            'requires' => ['php' => '^8.3', 'laravel' => '^12.0', 'lms' => '^1.0'],
            'dependencies' => ['aec.identity'],
            'conflicts' => [],
            'portals' => ['admin', 'branch', 'teacher', 'student'],
            'permissions' => ['courses.view', 'courses.manage'],
            'capabilities' => ['course-catalog', 'curriculum-builder'],
            'migrations' => 'database/migrations',
        ];
    }

    public function test_accepts_a_well_formed_declarative_manifest(): void
    {
        $this->assertTrue(app(PluginManifestValidator::class)->isValid($this->validManifest()));
    }


    public function test_accepts_explicit_support_staff_portal_for_accountant_role_mapping(): void
    {
        $manifest = $this->validManifest();
        $manifest['portals'][] = 'support_staff';

        $this->assertTrue(app(PluginManifestValidator::class)->isValid($manifest));
    }

    public function test_accepts_a_valid_typed_settings_schema(): void
    {
        $manifest = $this->validManifest();
        $manifest['settings_schema'] = [
            'api_endpoint' => ['label' => 'API endpoint', 'type' => 'url', 'required' => true],
            'api_secret' => ['label' => 'API secret', 'type' => 'secret'],
            'region' => ['label' => 'Region', 'type' => 'select', 'options' => ['asia', 'europe'], 'default' => 'asia'],
        ];

        $this->assertTrue(app(PluginManifestValidator::class)->isValid($manifest));
    }

    public function test_rejects_secret_defaults_and_defaults_that_do_not_match_their_type(): void
    {
        $manifest = $this->validManifest();
        $manifest['settings_schema'] = [
            'api_secret' => ['label' => 'API secret', 'type' => 'secret', 'default' => 'do-not-embed-secrets'],
            'endpoint' => ['label' => 'API endpoint', 'type' => 'url', 'default' => 'http://insecure.example.com'],
            'enabled' => ['label' => 'Enabled', 'type' => 'boolean', 'default' => 'yes'],
            'empty_text' => ['label' => 'Empty text', 'type' => 'text', 'default' => ''],
        ];

        $errors = app(PluginManifestValidator::class)->errors($manifest);

        $this->assertCount(4, array_filter($errors, fn (string $error): bool => str_contains($error, '.default is invalid')));
    }

    public function test_rejects_malformed_typed_settings_schema(): void
    {
        $manifest = $this->validManifest();
        $manifest['settings_schema'] = [
            'Invalid Key' => ['label' => 'Invalid', 'type' => 'text'],
            'region' => ['label' => 'Region', 'type' => 'select', 'options' => []],
            'secret' => ['label' => 'Secret', 'type' => 'secret', 'options' => ['not-allowed']],
        ];

        $errors = app(PluginManifestValidator::class)->errors($manifest);

        $this->assertContains('settings_schema contains an invalid setting key.', $errors);
        $this->assertContains('settings_schema.region.options must be a non-empty list.', $errors);
        $this->assertContains('settings_schema.secret.options is only valid for select settings.', $errors);
    }

    public function test_plugin_key_requires_vendor_and_plugin_segments(): void
    {
        $manifest = $this->validManifest();
        $manifest['key'] = 'course-management';

        $this->assertContains(
            'Invalid or missing plugin key.',
            app(PluginManifestValidator::class)->errors($manifest),
        );
    }

    public function test_rejects_unknown_fields_and_unsupported_schema_versions(): void
    {
        $manifest = $this->validManifest();
        $manifest['schema_version'] = 2;
        $manifest['boot_php'] = 'run-this.php';

        $errors = app(PluginManifestValidator::class)->errors($manifest);

        $this->assertContains('Unsupported or missing schema_version.', $errors);
        $this->assertContains('Unsupported manifest field: boot_php.', $errors);
    }

    public function test_rejects_path_traversal_absolute_paths_and_stream_wrappers(): void
    {
        foreach (['../Plugin.php', '/tmp/Plugin.php', 'phar://payload.php', 'src\\..\\Plugin.php'] as $path) {
            $manifest = $this->validManifest();
            $manifest['entrypoint'] = $path;

            $this->assertContains(
                'Entrypoint must be a safe relative PHP path.',
                app(PluginManifestValidator::class)->errors($manifest),
                "Path should be rejected: {$path}"
            );
        }
    }

    public function test_rejects_invalid_portals_duplicate_dependencies_and_self_dependency(): void
    {
        $manifest = $this->validManifest();
        $manifest['portals'] = ['admin', 'root'];
        $manifest['dependencies'] = ['aec.course-management', 'aec.course-management'];

        $errors = app(PluginManifestValidator::class)->errors($manifest);

        $this->assertContains('Manifest contains an unsupported portal.', $errors);
        $this->assertContains('dependencies must not contain duplicates.', $errors);
        $this->assertContains('A plugin cannot list itself in dependencies.', $errors);
    }

    public function test_rejects_invalid_semver_and_missing_compatibility_requirements(): void
    {
        $manifest = $this->validManifest();
        $manifest['version'] = 'latest';
        unset($manifest['requires']['laravel']);

        $errors = app(PluginManifestValidator::class)->errors($manifest);

        $this->assertContains('Invalid or missing semantic version.', $errors);
        $this->assertContains('Missing or invalid requires.laravel.', $errors);
    }
}
