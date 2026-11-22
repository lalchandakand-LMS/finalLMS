<?php

namespace App\Services\Gamification;

use App\Models\GamificationEvent;
use App\Models\GamificationProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GamificationService
{
    private const XP_REWARDS = [
        'lesson_completed' => 10,
        'quiz_passed' => 20,
        'course_completed' => 100,
        'attendance_verified' => 5,
        'scorm_completed' => 10,
    ];

    /**
     * Record a server-verified activity exactly once.
     * Callers must authorize the source record before invoking this method.
     */
    public function award(User $user, string $eventKey, string $sourceType, int $sourceId, ?Carbon $occurredAt = null): bool
    {
        if (! isset(self::XP_REWARDS[$eventKey]) || ! preg_match('/^[a-z][a-z0-9_]{1,39}$/', $sourceType) || $sourceId < 1) {
            throw new InvalidArgumentException('Unsupported gamification event.');
        }

        return DB::transaction(function () use ($user, $eventKey, $sourceType, $sourceId, $occurredAt): bool {
            DB::table('gamification_profiles')->insertOrIgnore([
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $profile = GamificationProfile::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $date = ($occurredAt ?? now())->copy()->startOfDay();
            $lastDate = $profile->current_streak_date?->copy()->startOfDay();

            $inserted = DB::table('gamification_events')->insertOrIgnore([
                'user_id' => $user->id,
                'event_key' => $eventKey,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'xp' => self::XP_REWARDS[$eventKey],
                'occurred_at' => $occurredAt ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted !== 1) {
                return false;
            }

            if (! $lastDate || $date->gt($lastDate->copy()->addDay())) {
                $profile->current_streak = 1;
            } elseif ($date->equalTo($lastDate)) {
                // Multiple verified activities on one local calendar day count as one streak day.
            } elseif ($date->equalTo($lastDate->copy()->addDay())) {
                $profile->current_streak++;
            }
            if (! $lastDate || $date->gt($lastDate)) {
                $profile->current_streak_date = $date;
            }
            $profile->longest_streak = max($profile->longest_streak, $profile->current_streak);
            $profile->save();

            if ($eventKey === 'lesson_completed'
                && GamificationEvent::query()->where('user_id', $user->id)->where('event_key', 'lesson_completed')->count() === 1) {
                $this->awardBadge($user, 'first_lesson');
            }
            if ($profile->current_streak >= 7) {
                $this->awardBadge($user, 'streak_7');
            }
            if ($eventKey === 'course_completed') {
                $this->awardBadge($user, 'course_finisher', ['source_id' => $sourceId]);
            }

            return true;
        }, 3);
    }

    public function awardBadge(User $user, string $badgeKey, array $metadata = []): bool
    {
        $badge = \App\Models\GamificationBadge::query()
            ->where('key', $badgeKey)->where('is_active', true)->firstOrFail();

        return DB::table('gamification_user_badges')->insertOrIgnore([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'awarded_at' => now(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    public function leaderboard(?int $branchId = null, int $limit = 50)
    {
        $limit = max(1, min($limit, 100));

        return DB::table('gamification_profiles as gp')
            ->join('users as u', 'u.id', '=', 'gp.user_id')
            ->leftJoin('gamification_events as ge', 'ge.user_id', '=', 'u.id')
            ->where('gp.leaderboard_opt_in', true)
            ->where('u.role', 'student')
            ->when($branchId !== null, fn ($query) => $query->where('u.branch_id', $branchId))
            ->groupBy('u.id', 'gp.display_name', 'gp.show_streak', 'gp.current_streak')
            ->selectRaw('u.id as learner_id, COALESCE(NULLIF(gp.display_name, ?), ?) as display_name, SUM(ge.xp) as xp', ['', 'Learner'])
            ->when(true, fn ($query) => $query->selectRaw('CASE WHEN gp.show_streak = 1 THEN gp.current_streak ELSE NULL END as streak'))
            ->orderByDesc('xp')->orderBy('u.id')
            ->limit($limit)->get()
            ->map(fn ($row) => (object) ['display_name' => $row->display_name, 'xp' => (int) $row->xp, 'streak' => $row->streak]);
    }
}
