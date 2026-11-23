<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceFeePeriod extends Model
{
    protected $fillable = [
        'batch_id', 'student_id', 'billing_period', 'attended_classes',
        'per_class_fee', 'amount', 'invoice_id', 'generated_by', 'generated_at',
    ];

    protected $casts = [
        'attended_classes' => 'integer',
        'per_class_fee' => 'decimal:2',
        'amount' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
