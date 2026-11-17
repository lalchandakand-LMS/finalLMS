<?php

namespace Aloms\LearnEarn;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnEarnExamEntitlement extends Model
{
    protected $table = 'learn_earn_exam_entitlements';

    protected $fillable = [
        'exam_id',
        'student_id',
        'payment_id',
        'status',
        'paid_at',
    ];

    protected $casts = ['paid_at' => 'datetime'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(LearnEarnExam::class, 'exam_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
