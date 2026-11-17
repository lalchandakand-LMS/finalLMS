<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use App\Models\User;
use Tests\TestCase;

class PrepQuestionBankPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        View::addNamespace('prep-question-bank', base_path('plugins/aec/bcs-bank-admission-question-bank/1.0.0/resources/views'));
        $migration = require base_path('plugins/aec/bcs-bank-admission-question-bank/1.0.0/migrations/2026_11_18_030000_create_prep_question_bank_tables.php');
        $migration->up();
        require_once base_path('plugins/aec/bcs-bank-admission-question-bank/1.0.0/src/PrepQuestionBankController.php');

        Route::middleware('auth')->prefix('__test-prep-bank')->name('admin.prep-question-bank.')->group(function (): void {
            Route::get('/', [\Aloms\PrepQuestionBank\PrepQuestionBankController::class, 'index'])->name('index');
            Route::post('/questions', [\Aloms\PrepQuestionBank\PrepQuestionBankController::class, 'storeQuestion'])->name('questions.store');
            Route::post('/sets', [\Aloms\PrepQuestionBank\PrepQuestionBankController::class, 'storeSet'])->name('sets.store');
            Route::get('/sets/{set}', [\Aloms\PrepQuestionBank\PrepQuestionBankController::class, 'showSet'])->name('sets.show');
        });
    }

    public function test_admin_can_create_mcq_and_written_questions_for_separate_exam_tracks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/__test-prep-bank/questions', [
            'track' => 'bcs', 'subject' => 'Bangla', 'topic' => 'Grammar', 'source_year' => 2025,
            'source_name' => 'BCS Preliminary', 'type' => 'mcq', 'difficulty' => 'medium',
            'question' => 'বাংলা ভাষার আদি নিদর্শন কোনটি?', 'options' => ['চর্যাপদ', 'শ্রীকৃষ্ণকীর্তন', 'মঙ্গলকাব্য', 'গীতাঞ্জলি'],
            'correct_option' => 0, 'marks' => 1,
        ])->assertRedirect(route('admin.prep-question-bank.index'));

        $this->actingAs($admin)->post('/__test-prep-bank/questions', [
            'track' => 'bank_job', 'subject' => 'English', 'topic' => 'Composition', 'type' => 'written',
            'difficulty' => 'hard', 'question' => 'Write a short note on financial inclusion.',
            'correct_answer' => 'A model answer', 'explanation' => 'Expected key points', 'marks' => 5,
        ])->assertRedirect(route('admin.prep-question-bank.index'));

        $this->assertDatabaseHas('aec_prep_questions', ['track' => 'bcs', 'type' => 'mcq', 'source_year' => 2025]);
        $this->assertDatabaseHas('aec_prep_questions', ['track' => 'bank_job', 'type' => 'written']);
    }

    public function test_mcq_requires_four_distinct_options_and_a_correct_option(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from('/__test-prep-bank')->post('/__test-prep-bank/questions', [
            'track' => 'admission', 'subject' => 'Physics', 'type' => 'mcq', 'difficulty' => 'easy',
            'question' => 'Which unit measures force?', 'options' => ['Newton', 'Newton', 'Joule', 'Watt'],
            'correct_option' => 0, 'marks' => 1,
        ])->assertSessionHasErrors('options');

        $this->assertSame(0, DB::table('aec_prep_questions')->count());
    }

    public function test_exam_paper_is_a_snapshot_of_selected_questions_and_random_count_is_respected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ids = [];
        foreach (['A', 'B', 'C'] as $letter) {
            $ids[] = DB::table('aec_prep_questions')->insertGetId([
                'track' => 'admission', 'subject' => 'Math', 'topic' => 'Algebra', 'type' => 'mcq',
                'difficulty' => 'medium', 'question' => 'Question '.$letter, 'options' => json_encode(['one', 'two', 'three', 'four']),
                'correct_option' => 1, 'marks' => 2, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->post('/__test-prep-bank/sets', [
            'track' => 'admission', 'title' => 'Admission Model Test 01', 'duration_minutes' => 60,
            'status' => 'ready', 'question_ids' => $ids, 'random_count' => 2,
        ]);
        $setId = (int) DB::table('aec_prep_question_sets')->value('id');

        $response->assertRedirect(route('admin.prep-question-bank.sets.show', $setId));
        $this->assertSame(2, DB::table('aec_prep_question_set_items')->where('question_set_id', $setId)->count());

        DB::table('aec_prep_questions')->where('id', $ids[0])->update(['question' => 'Changed source question']);
        $this->assertSame(2, DB::table('aec_prep_question_set_items')->where('question_set_id', $setId)->count());
        $this->assertDatabaseMissing('aec_prep_question_set_items', ['question_set_id' => $setId, 'question' => 'Changed source question']);
    }

    public function test_questions_from_another_exam_track_cannot_be_added_to_a_paper(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $id = DB::table('aec_prep_questions')->insertGetId([
            'track' => 'bcs', 'subject' => 'General Knowledge', 'type' => 'mcq', 'difficulty' => 'easy',
            'question' => 'Question?', 'options' => json_encode(['A', 'B', 'C', 'D']), 'correct_option' => 0,
            'marks' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post('/__test-prep-bank/sets', [
            'track' => 'bank_job', 'title' => 'Bank Exam', 'duration_minutes' => 30,
            'status' => 'draft', 'question_ids' => [$id],
        ])->assertForbidden();

        $this->assertSame(0, DB::table('aec_prep_question_sets')->count());
    }

    public function test_students_cannot_access_the_admin_plugin_routes(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get('/__test-prep-bank')->assertForbidden();
    }
}
