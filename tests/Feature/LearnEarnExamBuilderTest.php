<?php

namespace Tests\Feature;

use Aloms\LearnEarn\AdminLearnEarnExamController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnEarnExamBuilderTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnCategory.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnQuestion.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExam.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/LearnEarnExamQuestion.php');
        require_once base_path('plugins/aec/learn-earn/1.0.0/src/AdminLearnEarnExamController.php');

        $questionMigration = require base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_150000_create_learn_earn_question_tables.php');
        $questionMigration->up();
        $examMigration = require base_path('plugins/aec/learn-earn/1.0.0/migrations/2026_11_17_160000_create_learn_earn_exam_tables.php');
        $examMigration->up();

        Route::middleware('web')->post('/_test/learn-earn/exams', [AdminLearnEarnExamController::class, 'store']);
        Route::get('/admin/learn-earn/exams', fn () => 'ok')->name('admin.learn-earn.exams.index');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('learn_earn_exam_questions');
        Schema::dropIfExists('learn_earn_exams');
        Schema::dropIfExists('learn_earn_questions');
        Schema::dropIfExists('learn_earn_categories');

        parent::tearDown();
    }

    public function test_exam_builder_snapshots_selected_published_questions_atomically(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $categoryId = DB::table('learn_earn_categories')->insertGetId([
            'name' => 'Grammar',
            'slug' => 'grammar',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $questionId = DB::table('learn_earn_questions')->insertGetId([
            'category_id' => $categoryId,
            'created_by' => $admin->id,
            'subject' => 'Tense',
            'difficulty' => 'medium',
            'question' => 'Choose the correct verb.',
            'options' => json_encode(['go', 'goes', 'going', 'gone'], JSON_THROW_ON_ERROR),
            'correct_option' => 1,
            'marks' => 2,
            'negative_marks' => 1,
            'explanation' => 'Third-person singular.',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $startsAt = now()->addDay()->format('Y-m-d H:i:s');
        $endsAt = now()->addDays(2)->format('Y-m-d H:i:s');

        $response = $this->actingAs($admin)->post('/_test/learn-earn/exams', [
            'title' => 'Grammar Test',
            'description' => 'Basic tense test',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => 30,
            'pass_marks' => 1,
            'max_attempts' => 2,
            'randomize_questions' => 1,
            'access_type' => 'free',
            'price' => 0,
            'currency' => 'BDT',
            'status' => 'published',
            'show_results' => 1,
            'question_ids' => [$questionId],
        ]);

        $response->assertRedirect(route('admin.learn-earn.exams.index'));

        $exam = DB::table('learn_earn_exams')->where('slug', 'grammar-test')->first();
        $this->assertNotNull($exam);
        $this->assertSame('published', $exam->status);
        $this->assertSame('free', $exam->access_type);

        $snapshot = DB::table('learn_earn_exam_questions')->where('exam_id', $exam->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('Choose the correct verb.', $snapshot->question);
        $this->assertSame(2, (int) $snapshot->marks);
        $this->assertSame(1, (int) $snapshot->negative_marks);
        $this->assertSame('Grammar', $snapshot->category_label);

        DB::table('learn_earn_questions')->where('id', $questionId)->update([
            'question' => 'Changed source question',
            'marks' => 5,
        ]);

        $unchangedSnapshot = DB::table('learn_earn_exam_questions')->where('exam_id', $exam->id)->first();
        $this->assertSame('Choose the correct verb.', $unchangedSnapshot->question);
        $this->assertSame(2, (int) $unchangedSnapshot->marks);
    }

    public function test_exam_builder_rejects_pass_marks_above_the_lowest_random_question_total(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $questionIds = [];

        foreach ([1, 5] as $marks) {
            $questionIds[] = DB::table('learn_earn_questions')->insertGetId([
                'created_by' => $admin->id,
                'difficulty' => 'medium',
                'question' => 'Question '.$marks,
                'options' => json_encode(['A', 'B', 'C', 'D'], JSON_THROW_ON_ERROR),
                'correct_option' => 0,
                'marks' => $marks,
                'negative_marks' => 0,
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->post('/_test/learn-earn/exams', [
            'title' => 'Random marks test',
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
            'pass_marks' => 2,
            'questions_per_attempt' => 1,
            'max_attempts' => 1,
            'access_type' => 'free',
            'price' => 0,
            'currency' => 'BDT',
            'status' => 'draft',
            'question_ids' => $questionIds,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('learn_earn_exams', 0);
        $this->assertDatabaseCount('learn_earn_exam_questions', 0);
    }
}
