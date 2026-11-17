<?php

namespace Tests\Feature;

use App\Services\Plugins\PluginManifestValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertContains('admin', $manifest['portals']);
        $this->assertContains('student', $manifest['portals']);
        $this->assertContains('public', $manifest['portals']);
    }

    public function test_plugin_migrations_create_isolated_question_and_exam_tables(): void
    {
        $questionMigration = require base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_150000_create_learn_earn_question_tables.php');
        $examMigration = require base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_160000_create_learn_earn_exam_tables.php');

        $questionMigration->up();
        $examMigration->up();

        $this->assertDatabaseCount('learn_earn_categories', 0);
        $this->assertDatabaseCount('learn_earn_questions', 0);
        $this->assertDatabaseCount('learn_earn_exams', 0);
        $this->assertDatabaseCount('learn_earn_exam_questions', 0);
    }

    public function test_plugin_migration_rollback_refuses_to_drop_existing_learning_content(): void
    {
        $questionMigration = require base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_150000_create_learn_earn_question_tables.php');
        $examMigration = require base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_160000_create_learn_earn_exam_tables.php');

        $questionMigration->up();
        $examMigration->up();

        $categoryId = DB::table('learn_earn_categories')->insertGetId([
            'name' => 'English Grammar',
            'slug' => 'english-grammar',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $questionId = DB::table('learn_earn_questions')->insertGetId([
            'category_id' => $categoryId,
            'subject' => 'Tenses',
            'difficulty' => 'easy',
            'question' => 'Choose the correct verb.',
            'options' => json_encode(['go', 'goes', 'going', 'gone'], JSON_THROW_ON_ERROR),
            'correct_option' => 1,
            'marks' => 2,
            'negative_marks' => 1,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $examId = DB::table('learn_earn_exams')->insertGetId([
            'title' => 'Grammar Test',
            'slug' => 'grammar-test',
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'duration_minutes' => 30,
            'pass_marks' => 1,
            'max_attempts' => 1,
            'randomize_questions' => true,
            'access_type' => 'free',
            'price' => 0,
            'currency' => 'BDT',
            'status' => 'published',
            'show_results' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('learn_earn_exam_questions')->insert([
            'exam_id' => $examId,
            'source_question_id' => $questionId,
            'question' => 'Choose the correct verb.',
            'options' => json_encode(['go', 'goes', 'going', 'gone'], JSON_THROW_ON_ERROR),
            'correct_option' => 1,
            'marks' => 2,
            'negative_marks' => 1,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $examMigration->down();
            $this->fail('Exam migration rollback must preserve existing exam snapshots.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('preserve them', $exception->getMessage());
        }

        try {
            $questionMigration->down();
            $this->fail('Question migration rollback must preserve existing questions.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('preserve them', $exception->getMessage());
        }

        $this->assertDatabaseHas('learn_earn_questions', ['id' => $questionId]);
        $this->assertDatabaseHas('learn_earn_exam_questions', ['exam_id' => $examId]);
    }
}
