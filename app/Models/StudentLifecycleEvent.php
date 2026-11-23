<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLifecycleEvent extends Model
{
    protected $fillable = ['student_id', 'actor_id', 'event_type', 'before_data', 'after_data', 'reason'];

    protected function casts(): array
    {
        return ['before_data' => 'array', 'after_data' => 'array'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
