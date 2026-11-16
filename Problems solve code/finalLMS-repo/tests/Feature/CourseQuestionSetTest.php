<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseContentPost;
use App\Models\QuestionBankQuestion;
use App\Models\QuestionSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseQuestionSetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_question_set_listing_and_authoring_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.question-sets.index'))
            ->assertOk()->assertSee('Question sets');
        $this->get(route('admin.question-sets.create'))
            ->assertOk()->assertSee('Reuse Question Bank MCQs')
            ->assertSee('Short answer');
    }

    public function test_course_builder_preserves_existing_draft_posts_but_rejects_new_draft_attachments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $attachedDraft = CourseContentPost::create([
            'author_id' => $admin->id,
            'title' => 'Existing draft lesson',
            'slug' => 'existing-draft-lesson',
            'long_description' => 'Draft content already attached to this course.',
            'status' => 'draft',
            'tags' => [],
        ]);
        $newDraft = CourseContentPost::create([
            'author_id' => $admin->id,
            'title' => 'Unattached draft lesson',
            'slug' => 'unattached-draft-lesson',
            'long_description' => 'Draft content that is not attached.',
            'status' => 'draft',
            'tags' => [],
        ]);
        $course->curriculumItems()->create([
            'course_content_post_id' => $attachedDraft->id,
            'section_title' => 'Existing module',
            'position' => 10,
            'is_preview' => false,
            'duration_minutes' => 12,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.course-builder.edit', $course))
            ->assertOk()
            ->assertSee('Existing draft lesson')
            ->assertSee('Draft');

        $this->put(route('admin.course-builder.update', $course), [
            'post_ids' => [$attachedDraft->id],
            'section_titles' => [$attachedDraft->id => 'Existing module'],
            'positions' => [$attachedDraft->id => 20],
            'durations' => [$attachedDraft->id => 15],
            'previews' => [$attachedDraft->id => 0],
        ])->assertRedirect(route('admin.course-builder.edit', $course));

        $this->assertDatabaseHas('course_curriculum_items', [
            'course_id' => $course->id,
            'course_content_post_id' => $attachedDraft->id,
            'position' => 20,
        ]);

        $this->from(route('admin.course-builder.edit', $course))
            ->put(route('admin.course-builder.update', $course), [
                'post_ids' => [$newDraft->id],
            ])
            ->assertSessionHasErrors('post_ids.0');

        $this->assertDatabaseHas('course_curriculum_items', [
            'course_id' => $course->id,
            'course_content_post_id' => $attachedDraft->id,
        ]);
        $this->assertDatabaseMissing('course_curriculum_items', [
            'course_id' => $course->id,
            'course_content_post_id' => $newDraft->id,
        ]);
    }

    public function test_admin_can_create_a_mixed_question_set_and_snapshot_question_bank_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $bankQuestion = QuestionBankQuestion::create([
            'teacher_id' => $teacher->id,
            'subject' => 'Grammar',
            'category' => 'Tenses',
            'difficulty' => 'easy',
            'question' => 'Choose the past tense of go.',
            'options' => ['goes', 'went', 'gone', 'going'],
            'correct_option' => 1,
            'marks' => 2,
        ]);

        $this->actingAs($admin)->post(route('admin.question-sets.store'), [
            'title' => 'Grammar fundamentals',
            'description' => 'Core grammar practice.',
            'status' => 'published',
            'source_question_ids' => [$bankQuestion->id],
            'questions' => [
                [
                    'type' => 'true_false',
                    'question' => 'A noun names a person, place or thing.',
                    'correct_option' => 0,
                    'marks' => 1,
                    'difficulty' => 'easy',
                    'explanation' => 'A noun names a person, place or thing.',
                    'tags' => 'grammar, nouns',
                ],
                [
                    'type' => 'short_answer',
                    'question' => 'Write the plural of child.',
                    'correct_answer' => 'children',
                    'marks' => 2,
                    'difficulty' => 'medium',
                    'tags' => 'plural, nouns',
                ],
            ],
        ])->assertRedirect(route('admin.question-sets.index'));

        $set = QuestionSet::with('questions')->firstOrFail();
        $this->assertSame('published', $set->status);
        $this->assertCount(3, $set->questions);
        $this->assertSame('mcq', $set->questions[0]->type);
        $this->assertSame($bankQuestion->id, $set->questions[0]->source_question_id);
        $this->assertSame('true_false', $set->questions[1]->type);
        $this->assertSame(['True', 'False'], $set->questions[1]->options);
        $this->assertSame(0, $set->questions[1]->correct_option);
        $this->assertSame('short_answer', $set->questions[2]->type);
        $this->assertSame('children', $set->questions[2]->correct_answer);
        $this->assertSame(['plural', 'nouns'], $set->questions[2]->tags);

        $bankQuestion->update(['question' => 'Changed source bank wording.']);
        $this->assertSame('Choose the past tense of go.', $set->questions[0]->fresh()->question);
    }

    public function test_admin_can_update_question_set_and_rebuild_question_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $set = QuestionSet::create([
            'title' => 'Old set',
            'description' => 'Old description',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $oldQuestion = $set->questions()->create([
            'type' => 'short_answer',
            'question' => 'Old question?',
            'correct_answer' => 'old answer',
            'marks' => 1,
            'position' => 10,
        ]);

        $this->actingAs($admin)->put(route('admin.question-sets.update', $set), [
            'title' => 'Updated set',
            'description' => 'Updated description',
            'status' => 'published',
            'questions' => [
                [
                    'type' => 'mcq',
                    'question' => 'First new question?',
                    'options' => ['A', 'B', 'C', 'D'],
                    'correct_option' => 2,
                    'marks' => 3,
                    'position' => 20,
                ],
                [
                    'type' => 'short_answer',
                    'question' => 'Second new question?',
                    'correct_answer' => 'expected',
                    'marks' => 2,
                    'position' => 10,
                ],
            ],
        ])->assertRedirect(route('admin.question-sets.index'));

        $set->refresh()->load('questions');
        $this->assertSame('Updated set', $set->title);
        $this->assertSame('published', $set->status);
        $this->assertCount(2, $set->questions);
        $this->assertDatabaseMissing('question_set_questions', ['id' => $oldQuestion->id]);
        $this->assertSame('Second new question?', $set->questions[0]->question);
        $this->assertSame('First new question?', $set->questions[1]->question);
    }

    public function test_question_set_rejects_invalid_question_type_payloads(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.question-sets.create'))
            ->post(route('admin.question-sets.store'), [
                'title' => 'Invalid set',
                'status' => 'draft',
                'questions' => [
                    [
                        'type' => 'mcq',
                        'question' => 'Bad MCQ?',
                        'options' => ['Same', 'Same', 'Other', 'Last'],
                        'correct_option' => 0,
                        'marks' => 1,
                    ],
                ],
            ])->assertSessionHasErrors(['questions.0.options.1']);

        $this->from(route('admin.question-sets.create'))
            ->post(route('admin.question-sets.store'), [
                'title' => 'Invalid short answer',
                'status' => 'draft',
                'questions' => [
                    ['type' => 'short_answer', 'question' => 'Expected answer?', 'marks' => 1],
                ],
            ])->assertSessionHasErrors('questions');

        $this->assertDatabaseCount('question_sets', 0);
        $this->assertDatabaseCount('question_set_questions', 0);
    }

    public function test_admin_can_attach_published_question_set_to_course_module_with_attempt_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $set = QuestionSet::create([
            'title' => 'Module one quiz',
            'description' => 'Module quiz.',
            'status' => 'published',
            'created_by' => $admin->id,
        ]);
        $set->questions()->create([
            'type' => 'mcq',
            'question' => 'Pick A.',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0,
            'marks' => 1,
            'position' => 10,
        ]);

        $this->actingAs($admin)->put(route('admin.course-builder.update', $course), [
            'question_set_ids' => [$set->id],
            'question_set_section_titles' => [$set->id => 'Module 1'],
            'question_set_positions' => [$set->id => 20],
            'question_set_max_attempts' => [$set->id => 3],
            'question_set_time_limits' => [$set->id => 30],
            'question_set_questions_per_attempt' => [$set->id => 10],
            'question_set_randomize' => [$set->id => 0],
            'question_set_available_from' => [$set->id => now()->addDay()->format('Y-m-d H:i:s')],
        ])->assertRedirect(route('admin.course-builder.edit', $course));

        $this->assertDatabaseHas('course_question_set', [
            'course_id' => $course->id,
            'question_set_id' => $set->id,
            'section_title' => 'Module 1',
            'position' => 20,
            'max_attempts' => 3,
            'time_limit_minutes' => 30,
            'questions_per_attempt' => 10,
            'randomize_questions' => 0,
        ]);
        $this->actingAs($admin)->get(route('admin.course-builder.edit', $course))
            ->assertOk()->assertSee('Module one quiz')->assertSee('Module 1');

        $draft = QuestionSet::create([
            'title' => 'Draft set',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $this->from(route('admin.course-builder.edit', $course))
            ->put(route('admin.course-builder.update', $course), [
                'question_set_ids' => [$draft->id],
            ])
            ->assertSessionHasErrors('question_set_ids.0');

        $this->assertDatabaseHas('course_question_set', [
            'course_id' => $course->id,
            'question_set_id' => $set->id,
        ]);
        $this->assertDatabaseMissing('course_question_set', [
            'course_id' => $course->id,
            'question_set_id' => $draft->id,
        ]);
    }

    public function test_question_set_management_and_course_attachment_are_admin_only(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($student)->get(route('admin.question-sets.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.course-builder.edit', $course))->assertForbidden();
        $this->actingAs($student)->post(route('admin.question-sets.store'), [
            'title' => 'Student-created set',
            'status' => 'published',
        ])->assertForbidden();

        $this->assertDatabaseCount('question_sets', 0);
    }

    public function test_attached_question_set_is_archived_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $set = QuestionSet::create([
            'title' => 'Attached set',
            'status' => 'published',
            'created_by' => $admin->id,
        ]);
        $course->questionSets()->attach($set->id, ['position' => 10]);

        $this->actingAs($admin)->delete(route('admin.question-sets.destroy', $set))->assertRedirect();
        $this->assertDatabaseHas('question_sets', ['id' => $set->id, 'status' => 'archived']);
        $this->assertDatabaseHas('course_question_set', ['course_id' => $course->id, 'question_set_id' => $set->id]);
    }
}
