<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseMetadataAndCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function category(array $overrides = []): CourseCategory
    {
        return CourseCategory::create(array_merge([
            'name' => 'General English',
            'slug' => 'general-english',
            'description' => 'English learning courses',
            'is_visible' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    private function course(User $teacher, array $overrides = []): Course
    {
        static $number = 0;
        $number++;

        return Course::create(array_merge([
            'teacher_id' => $teacher->id,
            'title' => 'Course '.$number,
            'slug' => 'course-'.$number,
            'description' => 'Full course description.',
            'price' => 1500,
            'status' => 'published',
        ], $overrides));
    }

    public function test_admin_can_assign_managed_categories_and_save_complete_course_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = $this->category();

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'Everyday English',
            'short_description' => 'Build everyday speaking confidence.',
            'description' => 'A complete course for practical English communication.',
            'teacher_id' => $teacher->id,
            'price' => 2500,
            'status' => 'draft',
            'category_ids' => [$category->id],
            'tags' => 'speaking, grammar, vocabulary, speaking',
            'seo_title' => 'Everyday English Course',
            'seo_description' => 'Learn practical everyday English.',
            'learning_outcomes' => "Speak with confidence\nImprove listening",
            'requirements' => 'Basic literacy',
            'level' => 'Beginner',
            'language' => 'Bangla & English',
            'duration_minutes' => 720,
        ])->assertRedirect(route('admin.courses.index'));

        $course = Course::where('title', 'Everyday English')->firstOrFail();
        $this->assertTrue($course->categories()->whereKey($category->id)->exists());
        $this->assertSame(['speaking', 'grammar', 'vocabulary'], $course->tags);
        $this->assertSame('Everyday English Course', $course->seo_title);
        $this->assertSame('Build everyday speaking confidence.', $course->short_description);
        $this->assertSame(720, (int) $course->duration_minutes);
    }

    public function test_admin_course_form_exposes_category_learning_and_seo_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->category();
        $this->actingAs($admin)->get(route('admin.courses.create'))
            ->assertOk()
            ->assertSee('name="category_ids[]"', false)
            ->assertSee('name="learning_outcomes"', false)
            ->assertSee('name="requirements"', false)
            ->assertSee('name="seo_title"', false)
            ->assertSee('name="level"', false)
            ->assertSee('name="language"', false);
    }

    public function test_non_teacher_account_cannot_be_assigned_as_course_teacher(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->from(route('admin.courses.create'))
            ->post(route('admin.courses.store'), [
                'title' => 'Invalid Teacher Assignment',
                'description' => 'This course should not be saved.',
                'teacher_id' => $student->id,
                'price' => 100,
                'status' => 'draft',
            ])->assertSessionHasErrors('teacher_id');

        $this->assertDatabaseMissing('courses', ['title' => 'Invalid Teacher Assignment']);
    }

    public function test_course_slug_is_made_unique_when_titles_are_reused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $payload = [
            'title' => 'Same Course Title',
            'description' => 'Course description.',
            'teacher_id' => $teacher->id,
            'price' => 100,
            'status' => 'draft',
        ];

        $this->actingAs($admin)->post(route('admin.courses.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('admin.courses.store'), $payload)->assertRedirect();

        $this->assertDatabaseHas('courses', ['slug' => 'same-course-title']);
        $this->assertDatabaseHas('courses', ['slug' => 'same-course-title-2']);
    }

    public function test_course_with_enrollment_or_learning_history_is_archived_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course($teacher, ['title' => 'Historical Course', 'slug' => 'historical-course']);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->delete(route('admin.courses.destroy', $course))
            ->assertRedirect(route('admin.courses.index'));

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'status' => 'archived']);
        $this->assertDatabaseHas('enrollments', ['course_id' => $course->id, 'user_id' => $student->id]);
    }

    public function test_public_catalog_filters_direct_course_categories_level_and_language(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $grammar = $this->category(['name' => 'Grammar', 'slug' => 'grammar']);
        $speaking = $this->category(['name' => 'Speaking', 'slug' => 'speaking', 'sort_order' => 2]);

        $grammarCourse = $this->course($teacher, [
            'title' => 'Grammar Foundation',
            'slug' => 'grammar-foundation',
            'level' => 'Beginner',
            'language' => 'English',
            'tags' => ['grammar'],
        ]);
        $grammarCourse->categories()->attach($grammar->id);

        $speakingCourse = $this->course($teacher, [
            'title' => 'Speaking Practice',
            'slug' => 'speaking-practice',
            'level' => 'Intermediate',
            'language' => 'Bangla',
        ]);
        $speakingCourse->categories()->attach($speaking->id);

        $this->course($teacher, [
            'title' => 'Hidden Draft',
            'slug' => 'hidden-draft',
            'status' => 'draft',
            'level' => 'Beginner',
            'language' => 'English',
        ]);

        $this->get(route('courses.index', [
            'category' => $grammar->id,
            'level' => 'Beginner',
            'language' => 'English',
        ]))
            ->assertOk()
            ->assertSee('Grammar Foundation')
            ->assertDontSee('Speaking Practice')
            ->assertDontSee('Hidden Draft');
    }

    public function test_category_cannot_be_deleted_while_attached_to_a_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = $this->category();
        $course = $this->course($teacher);
        $course->categories()->attach($category->id);

        $this->actingAs($admin)->delete(route('admin.course-categories.destroy', $category))
            ->assertUnprocessable();

        $this->assertDatabaseHas('course_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('course_category_course', [
            'course_id' => $course->id,
            'course_category_id' => $category->id,
        ]);
    }

    public function test_public_course_page_renders_learning_outcomes_and_direct_categories(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = $this->category();
        $course = $this->course($teacher, [
            'title' => 'Speaking Confidence',
            'slug' => 'speaking-confidence',
            'short_description' => 'A practical speaking course.',
            'learning_outcomes' => "Speak clearly\nBuild confidence",
            'requirements' => 'Basic English literacy',
            'level' => 'Beginner',
            'language' => 'Bangla & English',
            'duration_minutes' => 480,
            'seo_title' => 'Speaking Confidence Course',
            'seo_description' => 'Learn to speak with confidence.',
        ]);
        $course->categories()->attach($category->id);

        $this->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Speaking Confidence Course')
            ->assertSee('What you will learn')
            ->assertSee('Speak clearly')
            ->assertSee('Basic English literacy')
            ->assertSee('General English');
    }
}
