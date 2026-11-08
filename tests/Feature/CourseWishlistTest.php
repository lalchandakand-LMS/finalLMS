<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseWishlistTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(User $teacher, string $title, string $status = 'published'): Course
    {
        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => $title,
            'slug' => str()->slug($title),
            'description' => 'A practical course description.',
            'price' => 1200,
            'status' => $status,
        ]);
    }

    public function test_student_can_add_course_once_and_view_their_wishlist(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = $this->makeCourse($teacher, 'Spoken English Starter');

        $this->actingAs($student)->post(route('student.wishlist.store', $course))->assertRedirect();
        $this->actingAs($student)->post(route('student.wishlist.store', $course))->assertRedirect();

        $this->assertSame(1, DB::table('course_wishlists')->where('user_id', $student->id)->where('course_id', $course->id)->count());

        $this->get(route('student.wishlist.index'))
            ->assertOk()
            ->assertSee('My Wishlist')
            ->assertSee('Spoken English Starter');
    }

    public function test_wishlist_is_private_to_each_student(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = $this->makeCourse($teacher, 'Private Wishlist Course');

        $this->actingAs($otherStudent)->post(route('student.wishlist.store', $course));
        $this->actingAs($student)->get(route('student.wishlist.index'))
            ->assertOk()
            ->assertDontSee('Private Wishlist Course');
    }

    public function test_student_can_remove_only_their_own_wishlist_entry(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = $this->makeCourse($teacher, 'Saved Course');

        $this->actingAs($student)->post(route('student.wishlist.store', $course));
        $this->actingAs($otherStudent)->delete(route('student.wishlist.destroy', $course))->assertRedirect();

        $this->assertDatabaseHas('course_wishlists', ['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($student)->delete(route('student.wishlist.destroy', $course))->assertRedirect();
        $this->assertDatabaseMissing('course_wishlists', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_only_students_can_use_wishlist_routes(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $owner = User::factory()->create(['role' => 'teacher']);
        $course = $this->makeCourse($owner, 'Teacher Course');

        $this->actingAs($teacher)->get(route('student.wishlist.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('student.wishlist.store', $course))->assertForbidden();
    }

    public function test_unpublished_courses_cannot_be_added_to_wishlist(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = $this->makeCourse($teacher, 'Draft Course', 'draft');

        $this->actingAs($student)->post(route('student.wishlist.store', $course))->assertNotFound();
        $this->assertDatabaseMissing('course_wishlists', ['user_id' => $student->id, 'course_id' => $course->id]);
    }
}
