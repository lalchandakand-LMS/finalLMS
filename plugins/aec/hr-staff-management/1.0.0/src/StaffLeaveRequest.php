<?php

declare(strict_types=1);

namespace Aloms\HrStaffManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffLeaveRequest extends Model
{
    protected $table = 'staff_leave_requests';
    protected $fillable = ['staff_profile_id', 'branch_id', 'leave_type', 'starts_on', 'ends_on', 'reason', 'status', 'review_note', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function staff(): BelongsTo { return $this->belongsTo(StaffProfile::class, 'staff_profile_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'reviewed_by'); }
}
