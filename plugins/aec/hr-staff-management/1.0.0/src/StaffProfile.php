<?php

declare(strict_types=1);

namespace Aloms\HrStaffManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StaffProfile extends Model
{
    protected $table = 'staff_profiles';
    protected $fillable = ['branch_id', 'user_id', 'employee_code', 'full_name', 'designation', 'department', 'phone', 'email', 'base_salary', 'status', 'joined_at', 'employment_type', 'contract_ends_at'];

    protected function casts(): array
    {
        return ['base_salary' => 'decimal:2', 'joined_at' => 'date', 'contract_ends_at' => 'date'];
    }

    public function payrolls(): HasMany { return $this->hasMany(StaffPayroll::class, 'staff_profile_id'); }
    public function attendances(): HasMany { return $this->hasMany(StaffAttendance::class, 'staff_profile_id'); }
    public function leaveRequests(): HasMany { return $this->hasMany(StaffLeaveRequest::class, 'staff_profile_id'); }
}
