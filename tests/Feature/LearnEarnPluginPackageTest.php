<?php

namespace Tests\Feature;

use App\Services\Plugins\PluginManifestValidator;
use Tests\TestCase;

class LearnEarnPluginPackageTest extends TestCase
{
    public function test_learn_earn_manifest_matches_the_signed_plugin_contract(): void
    {
        $manifestPath = base_path('plugins/aec/learn-earn/1.0.0/plugin.json');
        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('aec.learn-earn', $manifest['key']);
        $this->assertSame('src/Plugin.php', $manifest['entrypoint']);
        $this->assertTrue(app(PluginManifestValidator::class)->isValid($manifest));
    }

    public function test_plugin_migration_is_isolated_and_refuses_to_drop_existing_content(): void
    {
        $migrationPath = base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_150000_create_learn_earn_question_tables.php');
        $migrationSource = file_get_contents($migrationPath);

        $this->assertStringContainsString("Schema::create('learn_earn_categories'", $migrationSource);
        $this->assertStringContainsString("Schema::create('learn_earn_questions'", $migrationSource);
        $this->assertStringContainsString("'negative_marks'", $migrationSource);
        $this->assertStringContainsString('if ($hasQuestionData || $hasCategoryData)', $migrationSource);
        $this->assertStringContainsString('throw new RuntimeException', $migrationSource);
        $this->assertStringContainsString("Schema::dropIfExists('learn_earn_questions')", $migrationSource);
    }
}
