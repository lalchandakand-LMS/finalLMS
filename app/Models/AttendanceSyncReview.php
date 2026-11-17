<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSyncReview extends Model
{
    protected $fillable = [
        'idempotency_key',
        'teacher_id',
        'batch_id',
        'student_id',
        'attendance_date',
        'attendance_status',
        'location_latitude',
        'location_longitude',
        'location_accuracy_meters',
        'location_captured_at',
        'review_status',
        'reviewed_by',
        'review_reason',
        'reviewed_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'location_latitude' => 'decimal:7',
        'location_longitude' => 'decimal:7',
        'location_accuracy_meters' => 'decimal:2',
        'location_captured_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
