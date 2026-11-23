<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->string('compensation_basis', 20)->default('monthly');
            $table->decimal('per_class_rate', 12, 2)->default(0);
            $table->unsignedTinyInteger('working_days_per_month')->default(26);
        });

        Schema::table('staff_payrolls', function (Blueprint $table): void {
            $table->string('compensation_basis_snapshot', 20)->default('monthly');
            $table->decimal('present_days', 7, 2)->default(0);
            $table->decimal('absent_days', 7, 2)->default(0);
            $table->decimal('half_days', 7, 2)->default(0);
            $table->decimal('unpaid_leave_days', 7, 2)->default(0);
            $table->decimal('payable_days', 7, 2)->default(0);
            $table->unsignedSmallInteger('classes_delivered')->default(0);
            $table->decimal('attendance_deduction', 12, 2)->default(0);
            $table->unsignedTinyInteger('attendance_records_count')->default(0);
            $table->unsignedTinyInteger('expected_working_days')->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('staff_payrolls') && \Illuminate\Support\Facades\DB::table('staff_payrolls')->exists()) {
            throw new RuntimeException('Attendance-based payroll snapshots exist; retain them for financial audit history.');
        }

        Schema::table('staff_payrolls', function (Blueprint $table): void {
            $table->dropColumn([
                'compensation_basis_snapshot', 'present_days', 'absent_days', 'half_days',
                'unpaid_leave_days', 'payable_days', 'classes_delivered', 'attendance_deduction', 'attendance_records_count', 'expected_working_days',
            ]);
        });
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->dropColumn(['compensation_basis', 'per_class_rate', 'working_days_per_month']);
        });
    }
};
