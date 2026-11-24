<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GamificationBadge extends Model
{
    protected $fillable = ['key', 'name', 'description', 'icon', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gamification_user_badges', 'badge_id', 'user_id')
            ->withPivot(['awarded_at', 'metadata'])->withTimestamps();
    }
}
