<?php

namespace Tests\Feature;

use App\Services\Plugins\PluginManifestValidator;
use Tests\TestCase;

class FlashSalePluginPackageTest extends TestCase
{
    public function test_flash_sale_manifest_matches_the_signed_plugin_contract(): void
    {
        $manifestPath = base_path('plugins/aec/flash-sale/1.0.0/plugin.json');
        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('aec.flash-sale', $manifest['key']);
        $this->assertSame('src/Plugin.php', $manifest['entrypoint']);
        $this->assertSame(['admin', 'public'], $manifest['portals']);
        $this->assertContains('flash-sale.manage', $manifest['permissions']);
        $this->assertTrue(app(PluginManifestValidator::class)->isValid($manifest));
    }

    public function test_flash_sale_plugin_checks_shared_schema_and_preserves_it_on_uninstall(): void
    {
        $migration = file_get_contents(base_path('plugins/aec/flash-sale/1.0.0/migrations/2026_11_17_190000_validate_legacy_flash_sale_schema.php'));

        foreach (['flash_sales', 'flash_sale_course', 'flash_sale_course_category', 'payments', 'enrollments'] as $table) {
            $this->assertStringContainsString("'".$table."'", $migration);
        }

        $this->assertStringContainsString('Schema::hasTable($table)', $migration);
        $this->assertStringContainsString('Schema::hasColumn($table, $column)', $migration);
        $this->assertStringContainsString('Plugin uninstall must never delete or rewrite that data.', $migration);
    }
}
