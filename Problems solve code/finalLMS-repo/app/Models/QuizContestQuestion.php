<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizContestQuestion extends Model
{
    protected $fillable = ['quiz_contest_id', 'question', 'options', 'correct_option', 'position'];

    protected $casts = ['options' => 'array', 'correct_option' => 'integer', 'position' => 'integer'];

    public function contest(): BelongsTo
    {
        return $this->belongsTo(QuizContest::class, 'quiz_contest_id');
    }
}
