<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationProfile extends Model
{
    protected $fillable = ['user_id', 'leaderboard_opt_in', 'display_name', 'show_streak', 'current_streak_date', 'current_streak', 'longest_streak'];

    protected function casts(): array
    {
        return ['leaderboard_opt_in' => 'boolean', 'show_streak' => 'boolean', 'current_streak_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
