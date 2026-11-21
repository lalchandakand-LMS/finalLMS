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

    public function test_xp_award_is_idempotent_and_updates_daily_streak_once(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $service = app(GamificationService::class);
        $dayOne = now()->startOfDay();

        $this->assertTrue($service->award($student, 'lesson_completed', 'lesson', 101, $dayOne));
        $this->assertFalse($service->award($student, 'lesson_completed', 'lesson', 101, $dayOne));
        $this->assertTrue($service->award($student, 'lesson_completed', 'lesson', 102, $dayOne->copy()->addHours(2)));
        $this->assertTrue($service->award($student, 'quiz_passed', 'quiz', 201, $dayOne->copy()->addDay()));

        $this->assertSame(2, GamificationEvent::where('user_id', $student->id)->count());
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
        $badge = GamificationBadge::create(['key' => 'first_lesson', 'name' => 'First lesson', 'description' => 'Complete a lesson', 'is_active' => true]);
        $service = app(GamificationService::class);

        $this->assertTrue($service->awardBadge($student, $badge->key));
        $this->assertFalse($service->awardBadge($student, $badge->key));
        $this->assertDatabaseCount('gamification_user_badges', 1);
    }
}
