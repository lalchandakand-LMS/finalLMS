<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Course;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileCourseWishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_add_list_and_remove_wishlist_courses_idempotently(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Business English',
            'status' => 'published',
        ]);
        $token = $this->studentToken($student);
        $otherToken = $this->studentToken($otherStudent);
        $url = '/api/v1/courses/'.$course->slug.'/wishlist';

        $this->withToken($token)->postJson($url)
            ->assertOk()->assertJsonPath('wishlisted', true);
        $this->withToken($token)->postJson($url)
            ->assertOk()->assertJsonPath('wishlisted', true);

        $this->assertDatabaseCount('course_wishlists', 1);
        $this->withToken($token)->getJson('/api/v1/my/wishlist')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $course->id)
            ->assertJsonPath('data.0.slug', $course->slug);

        $this->withToken($token)->getJson('/api/v1/courses/'.$course->slug)
            ->assertOk()
            ->assertJsonPath('is_wishlisted', true);

        $this->withToken($otherToken)->getJson('/api/v1/my/wishlist')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($token)->deleteJson($url)
            ->assertOk()->assertJsonPath('wishlisted', false);
        $this->withToken($token)->deleteJson($url)
            ->assertOk()->assertJsonPath('wishlisted', false);

        $this->assertDatabaseMissing('course_wishlists', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
        $this->withToken($otherToken)->postJson($url)->assertOk();
        $this->assertDatabaseHas('course_wishlists', [
            'user_id' => $otherStudent->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_wishlist_rejects_unpublished_courses_and_hides_them_from_lists(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $draft = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'status' => 'draft',
        ]);
        $token = $this->studentToken($student);

        $this->withToken($token)->postJson('/api/v1/courses/'.$draft->slug.'/wishlist')
            ->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/my/wishlist')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->assertDatabaseCount('course_wishlists', 0);
    }

    public function test_wishlist_endpoints_are_student_only_and_require_a_valid_api_token(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'status' => 'published',
        ]);
        $teacherToken = $this->teacherToken($teacher);

        $this->getJson('/api/v1/my/wishlist')->assertUnauthorized();
        $this->withToken($teacherToken)->getJson('/api/v1/my/wishlist')->assertForbidden();
        $this->withToken($teacherToken)
            ->postJson('/api/v1/courses/'.$course->slug.'/wishlist')
            ->assertForbidden();
        $this->withToken($teacherToken)
            ->deleteJson('/api/v1/courses/'.$course->slug.'/wishlist')
            ->assertForbidden();

        $this->assertDatabaseCount('course_wishlists', 0);
    }

    private function studentToken(User $student): string
    {
        $token = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Wishlist test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        $this->storeToken($student, $token, $device);

        return $token;
    }

    private function teacherToken(User $teacher): string
    {
        $token = Str::random(80);
        $this->storeToken($teacher, $token);

        return $token;
    }

    private function storeToken(User $user, string $token, ?StudentDevice $device = null): ApiToken
    {
        return $user->apiTokens()->create([
            'student_device_id' => $device?->id,
            'name' => 'Wishlist API test',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);
    }
}
