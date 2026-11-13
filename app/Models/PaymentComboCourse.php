<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentComboCourse extends Model
{
    protected $fillable = ['payment_id', 'course_id', 'course_title', 'position'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
