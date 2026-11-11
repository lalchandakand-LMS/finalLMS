<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionRoom extends Model
{
    protected $fillable = ['created_by', 'name', 'description', 'visibility', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(DiscussionRoomMember::class, 'room_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DiscussionMessage::class, 'room_id');
    }
}
