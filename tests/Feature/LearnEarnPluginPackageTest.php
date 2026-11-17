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
        $this->assertSame(['admin'], $manifest['portals']);
        $this->assertTrue(app(PluginManifestValidator::class)->isValid($manifest));
    }

    public function test_plugin_migrations_are_isolated_and_refuse_to_drop_existing_content(): void
    {
        $questionMigration = file_get_contents(base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_150000_create_learn_earn_question_tables.php'));
        $examMigration = file_get_contents(base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_160000_create_learn_earn_exam_tables.php'));

        $this->assertStringContainsString("Schema::create('learn_earn_categories'", $questionMigration);
        $this->assertStringContainsString("Schema::create('learn_earn_questions'", $questionMigration);
        $this->assertStringContainsString("'negative_marks'", $questionMigration);
        $this->assertStringContainsString('if ($hasQuestionData || $hasCategoryData)', $questionMigration);
        $this->assertStringContainsString('throw new RuntimeException', $questionMigration);
        $this->assertStringContainsString("Schema::dropIfExists('learn_earn_questions')", $questionMigration);

        $this->assertStringContainsString("Schema::create('learn_earn_exams'", $examMigration);
        $this->assertStringContainsString("Schema::create('learn_earn_exam_questions'", $examMigration);
        $this->assertStringContainsString("'source_question_id'", $examMigration);
        $this->assertStringContainsString("'negative_marks'", $examMigration);
        $this->assertStringContainsString('throw new RuntimeException', $examMigration);
    }
}
