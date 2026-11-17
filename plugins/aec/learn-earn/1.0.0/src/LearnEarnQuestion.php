<?php

namespace Aloms\LearnEarn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnEarnQuestion extends Model
{
    protected $table = 'learn_earn_questions';

    protected $fillable = [
        'category_id',
        'created_by',
        'subject',
        'difficulty',
        'question',
        'options',
        'correct_option',
        'marks',
        'negative_marks',
        'explanation',
        'status',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_option' => 'integer',
        'marks' => 'integer',
        'negative_marks' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(LearnEarnCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
