<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('staff_profiles')) {
            Schema::create('staff_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('employee_code', 40)->unique();
                $table->string('full_name', 160);
                $table->string('designation', 120);
                $table->string('department', 100)->default('Academic');
                $table->string('phone', 40)->nullable();
                $table->string('email', 160)->nullable();
                $table->decimal('base_salary', 12, 2)->default(0);
                $table->string('status', 24)->default('active');
                $table->date('joined_at')->nullable();
                $table->timestamps();
                $table->index(['branch_id', 'status']);
            });
        }

        if (! Schema::hasTable('staff_payrolls')) {
            Schema::create('staff_payrolls', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_profile_id')->constrained('staff_profiles')->restrictOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('payroll_month', 7);
                $table->decimal('gross_salary', 12, 2);
                $table->decimal('bonus', 12, 2)->default(0);
                $table->decimal('deductions', 12, 2)->default(0);
                $table->decimal('net_paid', 12, 2);
                $table->string('status', 24)->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_reference', 120)->nullable();
                $table->timestamps();
                $table->unique(['staff_profile_id', 'payroll_month'], 'staff_payroll_staff_month_unique');
                $table->index(['branch_id', 'status', 'payroll_month']);
            });
        }
    }

    public function down(): void
    {
        // Staff and payroll history are financial records and are retained on uninstall.
    }
};
