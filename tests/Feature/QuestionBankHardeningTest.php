<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamAttempt;
use App\Models\QuestionBankQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_filter_update_and_delete_owned_question_bank_items(): void
    {
        [$teacher, , $batch] = $this->makeBatchWithStudent();
        $this->actingAs($teacher)->post(route('teacher.batches.question-bank.store', $batch), [
            'subject' => 'Grammar',
            'category' => 'Tenses',
            'difficulty' => 'easy',
            'question' => 'Choose the past tense of go.',
            'options' => ['goes', 'went', 'gone', 'going'],
            'correct_option' => 1,
            'marks' => 2,
        ])->assertRedirect();

        $question = QuestionBankQuestion::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame('Tenses', $question->category);
        $this->assertSame('easy', $question->difficulty);

        $this->get(route('teacher.batches.question-bank.index', [$batch, 'category' => 'Tenses', 'difficulty' => 'easy', 'search' => 'past']))
            ->assertOk()
            ->assertSee('Choose the past tense of go.');

        $this->put(route('teacher.batches.question-bank.update', [$batch, $question]), [
            'subject' => 'Grammar',
            'category' => 'Irregular verbs',
            'difficulty' => 'medium',
            'question' => 'Choose the past tense of eat.',
            'options' => ['eats', 'eaten', 'ate', 'eating'],
            'correct_option' => 2,
            'marks' => 3,
        ])->assertRedirect();

        $this->assertDatabaseHas('question_bank_questions', [
            'id' => $question->id,
            'category' => 'Irregular verbs',
            'difficulty' => 'medium',
            'question' => 'Choose the past tense of eat.',
            'marks' => 3,
        ]);

        $this->delete(route('teacher.batches.question-bank.destroy', [$batch, $question]))->assertRedirect();
        $this->assertDatabaseMissing('question_bank_questions', ['id' => $question->id]);
    }

    public function test_teacher_cannot_edit_or_delete_another_teachers_question_bank_item(): void
    {
        [$teacher, , $batch] = $this->makeBatchWithStudent();
        $owner = User::factory()->create(['role' => 'teacher']);
        $question = QuestionBankQuestion::create([
            'teacher_id' => $owner->id,
            'subject' => 'Vocabulary',
            'category' => 'Words',
            'difficulty' => 'easy',
            'question' => 'Meaning of diligent?',
            'options' => ['lazy', 'hardworking', 'careless', 'slow'],
            'correct_option' => 1,
            'marks' => 1,
        ]);

        $this->actingAs($teacher)->put(route('teacher.batches.question-bank.update', [$batch, $question]), [
            'subject' => 'Vocabulary',
            'category' => 'Words',
            'difficulty' => 'easy',
            'question' => 'Tampered question',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0,
            'marks' => 1,
        ])->assertForbidden();

        $this->delete(route('teacher.batches.question-bank.destroy', [$batch, $question]))->assertForbidden();
        $this->assertDatabaseHas('question_bank_questions', ['id' => $question->id, 'question' => 'Meaning of diligent?']);
    }

    public function test_question_bank_rejects_invalid_mcq_option_count_and_correct_answer_index(): void
    {
        [$teacher, , $batch] = $this->makeBatchWithStudent();

        $this->actingAs($teacher)->from(route('teacher.batches.question-bank.index', $batch))
            ->post(route('teacher.batches.question-bank.store', $batch), [
                'subject' => 'Grammar',
                'difficulty' => 'easy',
                'question' => 'Invalid MCQ?',
                'options' => ['Only one option'],
                'correct_option' => 4,
                'marks' => 1,
            ])
            ->assertSessionHasErrors(['options', 'correct_option']);

        $this->assertDatabaseCount('question_bank_questions', 0);
    }

    public function test_randomized_attempt_question_set_is_limited_and_stays_frozen_when_student_reopens_exam(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $secondStudent = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'user_id' => $secondStudent->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);

        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Randomized paper',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'questions_per_attempt' => 2,
            'status' => 'published',
        ]);

        foreach (range(1, 5) as $position) {
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'question' => 'Question '.$position,
                'options' => ['A', 'B', 'C', 'D'],
                'correct_option' => 0,
                'marks' => 1,
                'position' => $position,
            ]);
        }

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
        $firstSet = $attempt->attemptQuestions()->orderBy('position')->pluck('exam_question_id')->all();
        $this->assertCount(2, $firstSet);

        $this->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt->refresh();
        $this->assertSame($firstSet, $attempt->attemptQuestions()->orderBy('position')->pluck('exam_question_id')->all());

        $this->actingAs($secondStudent)->post(route('student.exams.start', $exam))->assertRedirect();
        $secondAttempt = ExamAttempt::where('exam_id', $exam->id)->where('student_id', $secondStudent->id)->firstOrFail();
        $this->assertCount(2, $secondAttempt->attemptQuestions);
    }

    public function test_admin_can_manage_assigned_teachers_question_bank_and_create_exam_from_it(): void
    {
        [$teacher, , $batch] = $this->makeBatchWithStudent();
        $admin = User::factory()->create(['role' => 'admin']);
        $ownedQuestion = QuestionBankQuestion::create([
            'teacher_id' => $teacher->id,
            'subject' => 'Grammar',
            'category' => 'Tenses',
            'difficulty' => 'easy',
            'question' => 'Admin-managed past tense question?',
            'options' => ['go', 'went', 'gone', 'going'],
            'correct_option' => 1,
            'marks' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.batches.question-bank.index', $batch))
            ->assertOk()
            ->assertSee('Admin-managed past tense question?');

        $this->actingAs($admin)
            ->post(route('admin.batches.question-bank.store', $batch), [
                'subject' => 'Vocabulary',
                'category' => 'Word meaning',
                'difficulty' => 'medium',
                'question' => 'Meaning of diligent?',
                'options' => ['lazy', 'hardworking', 'careless', 'slow'],
                'correct_option' => 1,
                'marks' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('question_bank_questions', [
            'teacher_id' => $teacher->id,
            'question' => 'Meaning of diligent?',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.batches.question-bank.update', [$batch, $ownedQuestion]), [
                'subject' => 'Grammar',
                'category' => 'Past tense',
                'difficulty' => 'hard',
                'question' => 'Select the correct past tense form?',
                'options' => ['go', 'went', 'gone', 'going'],
                'correct_option' => 1,
                'marks' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('question_bank_questions', [
            'id' => $ownedQuestion->id,
            'teacher_id' => $teacher->id,
            'category' => 'Past tense',
            'difficulty' => 'hard',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.batches.exams.store', $batch), [
                'title' => 'Admin Question Bank Exam',
                'instructions' => 'Generated from the assigned teacher question bank.',
                'starts_at' => now()->addHour()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
                'duration_minutes' => 30,
                'pass_marks' => 3,
                'question_bank_ids' => [$ownedQuestion->id],
                'random_question_count' => 1,
                'questions_per_attempt' => 1,
            ])
            ->assertRedirect();

        $exam = Exam::where('title', 'Admin Question Bank Exam')->firstOrFail();
        $this->assertSame((int) $teacher->id, (int) $exam->teacher_id);
        $this->assertDatabaseHas('exam_questions', [
            'exam_id' => $exam->id,
            'question' => 'Select the correct past tense form?',
            'marks' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.batches.exams.index', $batch))
            ->assertOk()
            ->assertSee('Admin Question Bank Exam');

        $this->actingAs($admin)
            ->delete(route('admin.batches.question-bank.destroy', [$batch, $ownedQuestion]))
            ->assertRedirect();

        $this->assertDatabaseMissing('question_bank_questions', ['id' => $ownedQuestion->id]);
        // The exam stores an immutable snapshot and remains valid after the source bank item is removed.
        $this->assertDatabaseHas('exam_questions', ['exam_id' => $exam->id, 'question' => 'Select the correct past tense form?']);
    }

    public function test_admin_cannot_access_or_use_question_bank_items_owned_by_another_batches_teacher(): void
    {
        [$teacher, , $batch] = $this->makeBatchWithStudent();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'admin']);
        $foreignQuestion = QuestionBankQuestion::create([
            'teacher_id' => $otherTeacher->id,
            'subject' => 'Private',
            'category' => 'Restricted',
            'difficulty' => 'hard',
            'question' => 'Question belonging to another teacher?',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0,
            'marks' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.batches.question-bank.index', $batch))
            ->assertOk()
            ->assertDontSee('Question belonging to another teacher?');

        $this->actingAs($admin)
            ->put(route('admin.batches.question-bank.update', [$batch, $foreignQuestion]), [
                'subject' => 'Changed',
                'category' => 'Changed',
                'difficulty' => 'easy',
                'question' => 'Attempted takeover?',
                'options' => ['A', 'B', 'C', 'D'],
                'correct_option' => 0,
                'marks' => 1,
            ])
            ->assertForbidden();

        $this->delete(route('admin.batches.question-bank.destroy', [$batch, $foreignQuestion]))
            ->assertForbidden();

        $this->post(route('admin.batches.exams.store', $batch), [
            'title' => 'Foreign question exam',
            'starts_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'question_bank_ids' => [$foreignQuestion->id],
            'random_question_count' => 1,
        ])->assertForbidden();

        $this->assertDatabaseHas('question_bank_questions', [
            'id' => $foreignQuestion->id,
            'teacher_id' => $otherTeacher->id,
            'question' => 'Question belonging to another teacher?',
        ]);
        $this->assertDatabaseCount('exams', 0);
        $this->assertSame((int) $teacher->id, (int) $batch->teacher_id);
    }

    public function test_question_bank_rejects_duplicate_mcq_options(): void
    {
        [$teacher, , $batch] = $this->makeBatchWithStudent();

        $this->actingAs($teacher)
            ->from(route('teacher.batches.question-bank.index', $batch))
            ->post(route('teacher.batches.question-bank.store', $batch), [
                'subject' => 'Grammar',
                'question' => 'Duplicate choices?',
                'options' => ['Same', 'Same', 'Other', 'Last'],
                'correct_option' => 0,
                'marks' => 1,
            ])
            ->assertSessionHasErrors('options.1');

        $this->assertDatabaseCount('question_bank_questions', 0);
    }

    private function makeBatchWithStudent(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Grammar',
            'slug' => 'grammar-'.uniqid(),
            'description' => 'Grammar practice.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Main Branch', 'slug' => 'main-'.uniqid()]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Morning Batch',
            'capacity' => 25,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);

        return [$teacher, $student, $batch];
    }
}
