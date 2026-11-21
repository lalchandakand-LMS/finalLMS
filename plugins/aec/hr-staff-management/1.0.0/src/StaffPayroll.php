<?php

declare(strict_types=1);

namespace Aloms\HrStaffManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffPayroll extends Model
{
    protected $table = 'staff_payrolls';
    protected $fillable = ['staff_profile_id', 'branch_id', 'payroll_month', 'gross_salary', 'bonus', 'deductions', 'net_paid', 'status', 'paid_at', 'payment_reference'];

    protected function casts(): array
    {
        return ['gross_salary' => 'decimal:2', 'bonus' => 'decimal:2', 'deductions' => 'decimal:2', 'net_paid' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }
}
