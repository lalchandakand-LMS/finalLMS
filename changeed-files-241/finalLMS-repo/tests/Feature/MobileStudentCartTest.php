<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileStudentCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_add_and_view_a_persistent_cart_item_with_server_pricing(): void
    {
        [$student, $course, $batch] = $this->fixture();
        $token = $this->token($student);

        $this->withToken($token)->postJson('/api/v1/my/cart', [
            'course_id' => $course->id,
            'batch_id' => $batch->id,
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/v1/my/cart')
            ->assertOk()
            ->assertJsonPath('meta.items_count', 1)
            ->assertJsonPath('data.0.course.slug', $course->slug)
            ->assertJsonPath('data.0.batch.id', $batch->id)
            ->assertJsonPath('data.0.pricing.base_amount', '2500.00')
            ->assertJsonPath('data.0.pricing.total', '2500.00')
            ->assertJsonPath('meta.total', '2500.00');

        $this->assertDatabaseHas('student_cart_items', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
        ]);
    }

    public function test_cart_is_private_and_student_cannot_remove_another_students_cart_item(): void
    {
        [$student, $course] = $this->fixture();
        $other = User::factory()->create(['role' => 'student']);
        $this->withToken($this->token($student))->postJson('/api/v1/my/cart', [
            'course_id' => $course->id,
        ])->assertCreated();

        $this->withToken($this->token($other))->getJson('/api/v1/my/cart')
            ->assertOk()
            ->assertJsonPath('meta.items_count', 0);

        $this->withToken($this->token($other))->deleteJson('/api/v1/my/cart/'.$course->slug)
            ->assertOk();

        $this->assertDatabaseHas('student_cart_items', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_cart_rejects_unpublished_courses_foreign_batches_and_enrolled_courses(): void
    {
        [$student, $course, $batch] = $this->fixture();
        $token = $this->token($student);
        $draft = Course::factory()->create(['status' => 'draft']);
        $otherCourse = Course::factory()->create(['status' => 'published']);

        $this->withToken($token)->postJson('/api/v1/my/cart', [
            'course_id' => $draft->id,
        ])->assertUnprocessable();

        $this->withToken($token)->postJson('/api/v1/my/cart', [
            'course_id' => $otherCourse->id,
            'batch_id' => $batch->id,
        ])->assertUnprocessable();

        $student->enrollments()->create([
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->withToken($token)->postJson('/api/v1/my/cart', [
            'course_id' => $course->id,
        ])->assertStatus(409);

        $this->assertDatabaseCount('student_cart_items', 0);
    }

    public function test_cart_item_can_be_removed_and_cart_cleared(): void
    {
        [$student, $course, $batch] = $this->fixture();
        $second = Course::factory()->create(['status' => 'published']);
        $token = $this->token($student);

        foreach ([$course, $second] as $item) {
            $this->withToken($token)->postJson('/api/v1/my/cart', [
                'course_id' => $item->id,
            ])->assertCreated();
        }

        $this->withToken($token)->deleteJson('/api/v1/my/cart/'.$course->slug)->assertOk();
        $this->assertDatabaseMissing('student_cart_items', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->withToken($token)->deleteJson('/api/v1/my/cart')->assertOk();
        $this->assertDatabaseCount('student_cart_items', 0);
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Persistent Cart Course',
            'status' => 'published',
            'price' => 3000,
        ]);
        $branch = Branch::create(['name' => 'Cart Branch', 'slug' => 'cart-branch']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Cart Batch',
            'capacity' => 20,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'fee' => 2500,
            'status' => 'open',
        ]);

        return [$student, $course, $batch];
    }

    private function token(User $student): string
    {
        $plain = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Cart API test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        ApiToken::create([
            'user_id' => $student->id,
            'student_device_id' => $device->id,
            'name' => 'Cart API test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
