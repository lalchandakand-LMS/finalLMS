<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\QuestionBankQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExaminationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_publishes_exam_and_enrolled_student_completes_single_attempt_with_automatic_scoring(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();

        $this->actingAs($teacher)->post(route('teacher.batches.exams.store', $batch), [
            'title' => 'English Grammar Test',
            'instructions' => 'Choose the correct answer.',
            'starts_at' => now()->addMinute()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
            'pass_marks' => 2,
            'questions' => [
                ['question' => 'Plural of child?', 'options' => ['childs', 'children', 'childes', 'child'], 'correct_option' => 1, 'marks' => 2],
                ['question' => 'Past of go?', 'options' => ['goed', 'gone', 'went', 'goes'], 'correct_option' => 2, 'marks' => 1],
            ],
        ])->assertRedirect();

        $exam = Exam::firstOrFail();
        $this->travelTo(now()->addMinutes(2));
        $this->assertDatabaseHas('exam_questions', ['exam_id' => $exam->id, 'question' => 'Plural of child?']);

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect(route('student.exams.show', $exam));
        $attempt = ExamAttempt::firstOrFail();

        $this->post(route('student.exams.submit', $attempt), [
            'answers' => [$exam->questions[0]->id => 1, $exam->questions[1]->id => 0],
        ])->assertRedirect(route('student.exams.show', $exam));

        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id, 'score' => 2, 'status' => 'submitted']);
        $this->assertDatabaseCount('exam_answers', 2);

        $this->post(route('student.exams.start', $exam))->assertSessionHas('info');
        $this->assertDatabaseCount('exam_attempts', 1);
    }

    public function test_student_cannot_access_exam_without_active_batch_enrollment(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent(false);
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Private test',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'status' => 'published',
        ]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
        $this->post(route('student.exams.start', $exam))->assertForbidden();
    }

    public function test_student_exam_list_only_includes_published_exams_for_active_enrollments(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $otherStudent = User::factory()->create(['role' => 'student']);
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Enrolled exam',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'status' => 'published',
        ]);

        $this->actingAs($student)->get(route('student.exams.index'))->assertOk()->assertSee('Enrolled exam');
        $this->actingAs($otherStudent)->get(route('student.exams.index'))->assertOk()->assertDontSee('Enrolled exam');
    }

    public function test_other_teacher_cannot_create_exam_for_batch(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($otherTeacher)->post(route('teacher.batches.exams.store', $batch), [
            'title' => 'Unauthorized exam',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'questions' => [['question' => 'Q?', 'options' => ['A','B','C','D'], 'correct_option' => 0, 'marks' => 1]],
        ])->assertForbidden();

        $this->assertDatabaseCount('exams', 0);
    }

    public function test_batch_teacher_can_view_submitted_exam_results_but_other_teacher_cannot(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Progress Test',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 5,
            'status' => 'published',
        ]);
        ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now()->subMinutes(5),
            'score' => 7,
            'status' => 'submitted',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.exams.results', $exam))
            ->assertOk()
            ->assertSee('Progress Test')
            ->assertSee($student->name)
            ->assertSee('Passed');

        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($otherTeacher)
            ->get(route('teacher.exams.results', $exam))
            ->assertForbidden();
    }

    public function test_teacher_can_build_exam_using_random_questions_from_owned_question_bank(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $bankQuestions = collect(range(1, 4))->map(fn ($number) => QuestionBankQuestion::create([
            'teacher_id' => $teacher->id,
            'subject' => 'Grammar',
            'question' => 'Question '.$number,
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 1,
            'marks' => 2,
        ]));

        $this->actingAs($teacher)->post(route('teacher.batches.exams.store', $batch), [
            'title' => 'Random Grammar Test',
            'instructions' => 'Randomized question set.',
            'starts_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
            'pass_marks' => 2,
            'question_bank_ids' => $bankQuestions->pluck('id')->all(),
            'random_question_count' => 2,
        ])->assertRedirect();

        $exam = Exam::where('title', 'Random Grammar Test')->firstOrFail();
        $this->assertDatabaseCount('exam_questions', 2);
        $this->assertSame(4, (int) $exam->questions()->sum('marks'));
    }

    public function test_teacher_cannot_use_another_teachers_question_bank_items(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $foreignQuestion = QuestionBankQuestion::create([
            'teacher_id' => $otherTeacher->id,
            'question' => 'Private question',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0,
            'marks' => 1,
        ]);

        $this->actingAs($teacher)->post(route('teacher.batches.exams.store', $batch), [
            'title' => 'Unauthorized bank exam',
            'starts_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'question_bank_ids' => [$foreignQuestion->id],
            'random_question_count' => 1,
        ])->assertForbidden();

        $this->assertDatabaseCount('exams', 0);
    }


    public function test_teacher_can_update_and_delete_questions_in_own_question_bank(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $question = QuestionBankQuestion::create([
            'teacher_id' => $teacher->id,
            'subject' => 'Grammar',
            'question' => 'Original question?',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0,
            'marks' => 1,
        ]);

        $this->actingAs($teacher)->put(route('teacher.batches.question-bank.update', [$batch, $question]), [
            'subject' => 'Tenses',
            'question' => 'Updated question?',
            'options' => ['One', 'Two', 'Three', 'Four'],
            'correct_option' => 2,
            'marks' => 3,
        ])->assertRedirect();

        $this->assertDatabaseHas('question_bank_questions', [
            'id' => $question->id,
            'subject' => 'Tenses',
            'question' => 'Updated question?',
            'correct_option' => 2,
            'marks' => 3,
        ]);

        $this->delete(route('teacher.batches.question-bank.destroy', [$batch, $question]))
            ->assertRedirect();
        $this->assertDatabaseMissing('question_bank_questions', ['id' => $question->id]);
    }

    public function test_teacher_cannot_edit_or_delete_another_teachers_question(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $question = QuestionBankQuestion::create([
            'teacher_id' => $otherTeacher->id,
            'subject' => 'Private',
            'question' => 'Protected question?',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0,
            'marks' => 1,
        ]);

        $this->actingAs($teacher)->put(route('teacher.batches.question-bank.update', [$batch, $question]), [
            'subject' => 'Changed',
            'question' => 'Tampered question?',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0,
            'marks' => 1,
        ])->assertForbidden();

        $this->delete(route('teacher.batches.question-bank.destroy', [$batch, $question]))
            ->assertForbidden();
        $this->assertDatabaseHas('question_bank_questions', ['id' => $question->id]);
    }

    public function test_teacher_can_filter_question_bank_by_subject_and_search_question_text(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        QuestionBankQuestion::create([
            'teacher_id' => $teacher->id, 'subject' => 'Grammar',
            'question' => 'Choose the past tense', 'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 1, 'marks' => 1,
        ]);
        QuestionBankQuestion::create([
            'teacher_id' => $teacher->id, 'subject' => 'Vocabulary',
            'question' => 'Meaning of diligent', 'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 2, 'marks' => 1,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.batches.question-bank.index', [$batch, 'subject' => 'Grammar']))
            ->assertOk()->assertSee('Choose the past tense')->assertDontSee('Meaning of diligent');

        $this->actingAs($teacher)
            ->get(route('teacher.batches.question-bank.index', [$batch, 'search' => 'diligent']))
            ->assertOk()->assertSee('Meaning of diligent')->assertDontSee('Choose the past tense');
    }

    public function test_question_bank_filter_does_not_expose_another_teachers_questions(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        QuestionBankQuestion::create([
            'teacher_id' => $otherTeacher->id, 'subject' => 'Grammar',
            'question' => 'Private teacher question', 'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0, 'marks' => 1,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.batches.question-bank.index', [$batch, 'subject' => 'Grammar']))
            ->assertOk()->assertDontSee('Private teacher question');
    }

    public function test_attempt_cannot_submit_answers_for_questions_outside_its_frozen_paper(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Frozen paper test',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'status' => 'published',
        ]);
        $assignedQuestion = ExamQuestion::create([
            'exam_id' => $exam->id,
            'question' => 'Assigned question?',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0,
            'marks' => 1,
            'position' => 1,
        ]);
        $foreignExam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Another paper',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'status' => 'published',
        ]);
        $foreignQuestion = ExamQuestion::create([
            'exam_id' => $foreignExam->id,
            'question' => 'Foreign question?',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0,
            'marks' => 1,
            'position' => 1,
        ]);

        $this->actingAs($student)
            ->post(route('student.exams.start', $exam))
            ->assertRedirect();

        $attempt = ExamAttempt::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();

        $this->post(route('student.exams.submit', $attempt), [
            'answers' => [$foreignQuestion->id => 0],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('exam_attempt_questions', [
            'exam_attempt_id' => $attempt->id,
            'exam_question_id' => $assignedQuestion->id,
        ]);
        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id, 'status' => 'in_progress']);
        $this->assertDatabaseCount('exam_answers', 0);
    }

    public function test_submitted_exam_attempt_cannot_be_submitted_twice(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Single submission test',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 1,
            'status' => 'published',
        ]);
        $question = ExamQuestion::create([
            'exam_id' => $exam->id,
            'question' => 'One plus one?',
            'options' => ['1', '2', '3', '4'],
            'correct_option' => 1,
            'marks' => 1,
            'position' => 1,
        ]);

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();

        $this->post(route('student.exams.submit', $attempt), [
            'answers' => [$question->id => 1],
        ])->assertRedirect();

        $this->post(route('student.exams.submit', $attempt), [
            'answers' => [$question->id => 0],
        ])->assertForbidden();

        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id, 'score' => 1, 'status' => 'submitted']);
        $this->assertDatabaseCount('exam_answers', 1);
    }

    public function test_enrolled_student_cannot_open_a_draft_exam_by_guessing_its_url(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Private draft exam',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($student)
            ->get(route('student.exams.show', $exam))
            ->assertNotFound();
    }

    public function test_student_can_autosave_exam_answers_and_resume_with_saved_selections(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Autosave and resume test',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 1,
            'status' => 'published',
        ]);
        $question = ExamQuestion::create([
            'exam_id' => $exam->id,
            'question' => 'Choose the correct answer?',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 2,
            'marks' => 1,
            'position' => 1,
        ]);

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();

        $this->postJson(route('student.exams.answers.save', $attempt), [
            'answers' => [$question->id => 2],
        ])->assertOk()->assertJson(['saved' => 1]);

        $this->assertDatabaseHas('exam_answers', [
            'exam_attempt_id' => $attempt->id,
            'exam_question_id' => $question->id,
            'selected_option' => 2,
            'is_correct' => false,
            'marks_awarded' => 0,
        ]);

        $this->get(route('student.exams.show', $exam))
            ->assertOk()
            ->assertSee('value="2" checked', false);

        $this->post(route('student.exams.submit', $attempt), [
            'answers' => [$question->id => 2],
        ])->assertRedirect();

        $this->assertDatabaseHas('exam_answers', [
            'exam_attempt_id' => $attempt->id,
            'exam_question_id' => $question->id,
            'selected_option' => 2,
            'is_correct' => true,
            'marks_awarded' => 1,
        ]);
        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id, 'score' => 1, 'status' => 'submitted']);
        $this->assertDatabaseCount('exam_answers', 1);
    }

    public function test_student_cannot_autosave_an_answer_for_a_question_outside_the_frozen_attempt(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Autosave isolation test',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'status' => 'published',
        ]);
        $ownedQuestion = ExamQuestion::create([
            'exam_id' => $exam->id, 'question' => 'Owned?', 'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0, 'marks' => 1, 'position' => 1,
        ]);
        $otherExam = Exam::create([
            'batch_id' => $batch->id, 'teacher_id' => $teacher->id, 'title' => 'Different exam',
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
            'duration_minutes' => 30, 'pass_marks' => 0, 'status' => 'published',
        ]);
        $foreignQuestion = ExamQuestion::create([
            'exam_id' => $otherExam->id, 'question' => 'Not assigned?', 'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0, 'marks' => 1, 'position' => 1,
        ]);

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();

        $this->postJson(route('student.exams.answers.save', $attempt), [
            'answers' => [$foreignQuestion->id => 0],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('exam_answers', [
            'exam_attempt_id' => $attempt->id,
            'exam_question_id' => $foreignQuestion->id,
        ]);
        $this->assertDatabaseHas('exam_attempt_questions', [
            'exam_attempt_id' => $attempt->id,
            'exam_question_id' => $ownedQuestion->id,
        ]);
    }
    private function makeBatchWithStudent(bool $enroll = true): array
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
        if ($enroll) Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);

        return [$teacher, $student, $batch];
    }
}
