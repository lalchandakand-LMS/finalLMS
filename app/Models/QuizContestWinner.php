<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizContestWinner extends Model
{
    protected $fillable = ['quiz_contest_id', 'quiz_contest_entry_id', 'user_id', 'rank', 'prize_snapshot', 'drawn_by', 'drawn_at'];

    protected $casts = ['rank' => 'integer', 'drawn_at' => 'datetime'];

    public function contest(): BelongsTo
    {
        return $this->belongsTo(QuizContest::class, 'quiz_contest_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(QuizContestEntry::class, 'quiz_contest_entry_id');
    }
}
