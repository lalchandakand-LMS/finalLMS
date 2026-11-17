<?php

namespace Tests\Feature;

use App\Services\Plugins\PluginManifestValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LearnEarnPluginPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_earn_manifest_matches_the_signed_plugin_contract(): void
    {
        $manifestPath = base_path('plugins/aec/learn-earn/1.0.0/plugin.json');
        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('aec.learn-earn', $manifest['key']);
        $this->assertSame('src/Plugin.php', $manifest['entrypoint']);
        $this->assertTrue(app(PluginManifestValidator::class)->isValid($manifest));
    }

    public function test_plugin_migration_creates_isolated_mcq_and_category_tables(): void
    {
        $migration = require base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_150000_create_learn_earn_question_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('learn_earn_categories'));
        $this->assertTrue(Schema::hasTable('learn_earn_questions'));
        $this->assertTrue(Schema::hasColumns('learn_earn_questions', [
            'category_id',
            'created_by',
            'question',
            'options',
            'correct_option',
            'marks',
            'negative_marks',
            'explanation',
            'status',
        ]));

        $categoryId = DB::table('learn_earn_categories')->insertGetId([
            'name' => 'Grammar',
            'slug' => 'grammar',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('learn_earn_questions')->insert([
            'category_id' => $categoryId,
            'subject' => 'Tense',
            'difficulty' => 'medium',
            'question' => 'Choose the correct form.',
            'options' => json_encode(['go', 'goes', 'going', 'gone'], JSON_THROW_ON_ERROR),
            'correct_option' => 1,
            'marks' => 2,
            'negative_marks' => 1,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('learn_earn_questions', [
            'category_id' => $categoryId,
            'marks' => 2,
            'negative_marks' => 1,
            'status' => 'published',
        ]);
    }

    public function test_plugin_migration_refuses_to_drop_existing_learning_content(): void
    {
        $migration = require base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_150000_create_learn_earn_question_tables.php');
        $migration->up();

        DB::table('learn_earn_categories')->insert([
            'name' => 'Vocabulary',
            'slug' => 'vocabulary',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $migration->down();
    }
}
