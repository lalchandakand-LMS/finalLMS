<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['batch_id', 'student_id', 'marked_by', 'attendance_date', 'status', 'idempotency_key', 'location_latitude', 'location_longitude', 'location_accuracy_meters', 'location_captured_at'];

    protected $casts = [
        'attendance_date' => 'date',
        'location_latitude' => 'decimal:7',
        'location_longitude' => 'decimal:7',
        'location_accuracy_meters' => 'decimal:2',
        'location_captured_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}