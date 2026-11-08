<?php

namespace Tests\\Feature;

use App\\Models\\CommunityPost;
use App\\Models\\TeacherFollow;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class CommunityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_student_can_publish_like_and_comment_on_public_posts(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $this->actingAs($student);

        $this->post(route('community.posts.store'), ['body' => 'আজকের ক্লাসটি খুব ভালো হয়েছে।'])
            ->assertRedirect(route('community.index'));

        $post = CommunityPost::query()->firstOrFail();

        $this->post(route('community.posts.like', $post))->assertRedirect();
        $this->assertDatabaseHas('community_post_likes', ['post_id' => $post->id, 'user_id' => $student->id]);

        $this->post(route('community.posts.comment', $post), ['body' => 'ধন্যবাদ শিক্ষক।'])
            ->assertRedirect();
        $this->assertDatabaseHas('community_post_comments', ['post_id' => $post->id, 'user_id' => $student->id]);
    }

    public function test_student_can_follow_only_teacher_accounts(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $teacher = User::factory()->create(['role' => 'teacher', 'email_verified_at' => now()]);
        $anotherStudent = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)
            ->post(route('community.teachers.follow', $teacher->id))
            ->assertRedirect();

        $this->assertDatabaseHas('teacher_follows', [
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
        ]);

        $this->post(route('community.teachers.follow', $anotherStudent->id))->assertNotFound();
        $this->delete(route('community.teachers.unfollow', $teacher->id))->assertRedirect();
        $this->assertDatabaseMissing('teacher_follows', [
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_non_student_cannot_follow_a_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->post(route('community.teachers.follow', $teacher->id))
            ->assertForbidden();

        $this->assertSame(0, TeacherFollow::query()->count());
    }
}
