<?php

declare(strict_types=1);

namespace Aloms\HrStaffManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StaffPayroll extends Model
{
    protected $table = 'staff_payrolls';
    protected $fillable = ['staff_profile_id', 'branch_id', 'payroll_month', 'basic_salary', 'allowances', 'overtime_hours', 'overtime_rate', 'overtime_amount', 'gross_salary', 'bonus', 'deductions', 'net_paid', 'status', 'compensation_basis_snapshot', 'present_days', 'absent_days', 'half_days', 'unpaid_leave_days', 'payable_days', 'classes_delivered', 'attendance_deduction', 'paid_at', 'payment_reference'];

    protected function casts(): array
    {
        return ['basic_salary' => 'decimal:2', 'allowances' => 'decimal:2', 'overtime_hours' => 'decimal:2', 'overtime_rate' => 'decimal:2', 'overtime_amount' => 'decimal:2', 'gross_salary' => 'decimal:2', 'bonus' => 'decimal:2', 'deductions' => 'decimal:2', 'net_paid' => 'decimal:2', 'present_days' => 'decimal:2', 'absent_days' => 'decimal:2', 'half_days' => 'decimal:2', 'unpaid_leave_days' => 'decimal:2', 'payable_days' => 'decimal:2', 'classes_delivered' => 'integer', 'attendance_deduction' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }
}
