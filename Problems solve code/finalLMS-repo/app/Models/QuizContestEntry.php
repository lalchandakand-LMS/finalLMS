<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizContestEntry extends Model
{
    protected $fillable = ['quiz_contest_id', 'user_id', 'answers', 'score', 'submitted_at'];

    protected $casts = ['answers' => 'array', 'score' => 'integer', 'submitted_at' => 'datetime'];

    public function contest(): BelongsTo
    {
        return $this->belongsTo(QuizContest::class, 'quiz_contest_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
