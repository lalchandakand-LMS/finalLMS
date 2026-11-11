<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionMessage extends Model
{
    protected $fillable = ['room_id', 'user_id', 'body', 'reply_to_id'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(DiscussionRoom::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
