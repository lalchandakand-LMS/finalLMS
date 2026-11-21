<?php

namespace App\\Http\\Controllers;

use App\\Models\\GamificationBadge;
use App\\Models\\GamificationEvent;
use App\\Models\\GamificationProfile;
use App\\Services\\Gamification\\GamificationService;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\View\\View;

class GamificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403);

        $profile = GamificationProfile::query()->firstOrCreate(['user_id' => $user->id]);
        $xp = GamificationEvent::query()->where('user_id', $user->id)->sum('xp');
        $badges = GamificationBadge::query()
            ->where('is_active', true)
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->get(['gamification_badges.id', 'key', 'name', 'description', 'icon']);

        return view('student.gamification.index', compact('profile', 'xp', 'badges'));
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isStudent(), 403);

        $validated = $request->validate([
            'leaderboard_opt_in' => ['required', 'boolean'],
            // A public leaderboard entry must always use a learner-chosen nickname.
            'display_name' => ['required_if:leaderboard_opt_in,1', 'nullable', 'string', 'min:2', 'max:40', 'regex:/^[\\pL\\pN _.-]+$/u'],
            'show_streak' => ['required', 'boolean'],
        ]);

        if (! $validated['leaderboard_opt_in']) {
            $validated['display_name'] = null;
            $validated['show_streak'] = false;
        }

        GamificationProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return back()->with('success', 'Gamification privacy preferences updated.');
    }

    public function leaderboard(Request $request, GamificationService $gamification): View
    {
        abort_unless($request->user()?->isStudent(), 403);

        $rows = $gamification->leaderboard(null, 50);

        return view('student.gamification.leaderboard', compact('rows'));
    }
}
