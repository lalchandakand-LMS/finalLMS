<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'order_id',
        'course_combo_id',
        'flash_sale_id',
        'batch_id',
        'gateway_provider',
        'gateway_payment_id',
        'gateway_transaction_id',
        'method',
        'amount',
        'currency',
        'sender_account',
        'transaction_reference',
        'proof_path',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function courseCombo(): BelongsTo
    {
        return $this->belongsTo(CourseCombo::class);
    }

    public function comboCourses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentComboCourse::class)->orderBy('position');
    }
}