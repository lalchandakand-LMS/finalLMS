<?php

namespace Tests\Feature;

use App\Models\GamificationBadge;
use App\Models\GamificationEvent;
use App\Models\GamificationProfile;
use App\Models\User;
use App\Services\Gamification\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_student_accounts_cannot_receive_xp_or_badges(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $service = app(GamificationService::class);

        try {
            $service->award($teacher, 'lesson_completed', 'lesson', 901);
            $this->fail('A teacher account must not receive learner XP.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Gamification rewards can only be awarded to students.', $exception->getMessage());
        }

        try {
            $service->awardBadge($teacher, 'first_lesson');
            $this->fail('A teacher account must not receive learner badges.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Gamification badges can only be awarded to students.', $exception->getMessage());
        }

        $this->assertDatabaseCount('gamification_events', 0);
        $this->assertDatabaseCount('gamification_user_badges', 0);
    }

    public function test_xp_award_is_idempotent_and_updates_daily_streak_once(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $service = app(GamificationService::class);
        $dayOne = now()->startOfDay();

        $this->assertTrue($service->award($student, 'lesson_completed', 'lesson', 101, $dayOne));
        $this->assertFalse($service->award($student, 'lesson_completed', 'lesson', 101, $dayOne));
        $this->assertTrue($service->award($student, 'lesson_completed', 'lesson', 102, $dayOne->copy()->addHours(2)));
        $this->assertTrue($service->award($student, 'quiz_passed', 'quiz', 201, $dayOne->copy()->addDay()));

        $this->assertSame(3, GamificationEvent::where('user_id', $student->id)->count());
        $profile = GamificationProfile::where('user_id', $student->id)->firstOrFail();
        $this->assertSame(2, $profile->current_streak);
        $this->assertSame(2, $profile->longest_streak);
    }

    public function test_leaderboard_includes_only_opted_in_students_and_hides_private_fields(): void
    {
        $visible = User::factory()->create(['role' => 'student', 'name' => 'Private Real Name', 'email' => 'private@example.test']);
        $hidden = User::factory()->create(['role' => 'student']);
        GamificationProfile::create(['user_id' => $visible->id, 'leaderboard_opt_in' => true, 'display_name' => 'Learner 7']);
        GamificationProfile::create(['user_id' => $hidden->id, 'leaderboard_opt_in' => false, 'display_name' => 'Hidden Learner']);

        $service = app(GamificationService::class);
        $service->award($visible, 'lesson_completed', 'lesson', 501);
        $service->award($hidden, 'lesson_completed', 'lesson', 502);

        $rows = $service->leaderboard();
        $this->assertCount(1, $rows);
        $this->assertSame('Learner 7', $rows[0]->display_name);
        $this->assertSame(10, $rows[0]->xp);
        $this->assertObjectNotHasProperty('email', $rows[0]);
        $this->assertObjectNotHasProperty('learner_id', $rows[0]);
    }

    public function test_badge_awards_are_idempotent(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $badge = GamificationBadge::create(['key' => 'custom_test_badge', 'name' => 'First lesson', 'description' => 'Complete a lesson', 'is_active' => true]);
        $service = app(GamificationService::class);

        $this->assertTrue($service->awardBadge($student, $badge->key));
        $this->assertFalse($service->awardBadge($student, $badge->key));
        $this->assertDatabaseCount('gamification_user_badges', 1);
    }

    public function test_disabled_optional_badge_does_not_block_verified_xp(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        GamificationBadge::query()->where('key', 'first_lesson')->update(['is_active' => false]);

        $service = app(GamificationService::class);

        $this->assertTrue($service->award($student, 'lesson_completed', 'lesson', 701));
        $this->assertDatabaseHas('gamification_events', [
            'user_id' => $student->id,
            'event_key' => 'lesson_completed',
            'source_id' => 701,
            'xp' => 10,
        ]);
        $this->assertDatabaseCount('gamification_user_badges', 0);
    }

    public function test_leaderboard_opt_in_requires_a_learner_chosen_display_name(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->from('/my/gamification')->put('/my/gamification/preferences', [
            'leaderboard_opt_in' => '1',
            'show_streak' => '0',
        ]);

        $response->assertRedirect('/my/gamification');
        $response->assertSessionHasErrors('display_name');
        $this->assertDatabaseMissing('gamification_profiles', ['user_id' => $student->id]);
    }

    public function test_private_leaderboard_preferences_do_not_require_a_display_name(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->from('/my/gamification')->put('/my/gamification/preferences', [
            'leaderboard_opt_in' => '0',
            'show_streak' => '1',
        ]);

        $response->assertRedirect('/my/gamification');
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('gamification_profiles', [
            'user_id' => $student->id,
            'leaderboard_opt_in' => false,
            'display_name' => null,
            'show_streak' => false,
        ]);
    }
}
