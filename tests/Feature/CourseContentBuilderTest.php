<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuestionBankQuestion;
use App\Models\QuestionSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseContentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function courseFor(User $teacher): Course
    {
        return Course::create([
            'teacher_id'=>$teacher->id,
            'title'=>'English Mastery',
            'slug'=>'english-mastery',
            'description'=>'Course description',
            'price'=>2000,
            'status'=>'published',
        ]);
    }

    public function test_teacher_can_create_curriculum_section_and_assign_lesson(): void
    {
        $teacher = User::factory()->create(['role'=>'teacher']);
        $course = $this->courseFor($teacher);

        $this->actingAs($teacher)->post(route('teacher.courses.sections.store',$course), [
            'title'=>'Module 1: Foundations',
            'description'=>'Start here',
            'position'=>1,
            'status'=>'published',
        ])->assertRedirect();

        $section = $course->sections()->firstOrFail();
        $this->actingAs($teacher)->post(route('teacher.courses.lessons.store',$course), [
            'section_id'=>$section->id,
            'title'=>'Introduction',
            'content'=>'Lesson content',
            'duration_minutes'=>20,
            'order'=>1,
            'is_preview'=>1,
        ])->assertRedirect();

        $this->assertDatabaseHas('lessons', [
            'course_id'=>$course->id,
            'section_id'=>$section->id,
            'title'=>'Introduction',
        ]);
    }

    public function test_admin_can_build_question_set_from_existing_question_bank(): void
    {
        $admin = User::factory()->create(['role'=>'admin']);
        $teacher = User::factory()->create(['role'=>'teacher']);
        $question = QuestionBankQuestion::create([
            'teacher_id'=>$teacher->id,
            'subject'=>'Grammar',
            'category'=>'Tense',
            'difficulty'=>'easy',
            'question'=>'Choose the correct verb.',
            'options'=>['go','goes','going','gone'],
            'correct_option'=>1,
            'marks'=>2,
        ]);

        $this->actingAs($admin)->post(route('admin.question-sets.store'), [
            'title'=>'Grammar Basics',
            'description'=>'Starter questions',
            'status'=>'published',
            'question_ids'=>[$question->id],
        ])->assertRedirect();

        $set = QuestionSet::where('title','Grammar Basics')->firstOrFail();
        $this->assertDatabaseHas('question_set_question', [
            'question_set_id'=>$set->id,
            'question_bank_question_id'=>$question->id,
            'position'=>1,
        ]);
    }
}
