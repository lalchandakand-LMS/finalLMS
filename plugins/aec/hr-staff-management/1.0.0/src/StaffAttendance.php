<?php

declare(strict_types=1);

namespace Aloms\HrStaffManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffAttendance extends Model
{
    protected $table = 'staff_attendances';
    protected $fillable = ['staff_profile_id', 'branch_id', 'attendance_date', 'status', 'check_in_at', 'check_out_at', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date'];
    }

    public function staff(): BelongsTo { return $this->belongsTo(StaffProfile::class, 'staff_profile_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'recorded_by'); }
}
