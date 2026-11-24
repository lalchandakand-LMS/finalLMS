<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff_profiles', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('branch_id')->unique()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('staff_profiles', 'employment_type')) {
                $table->string('employment_type', 24)->default('full_time');
            }
            if (! Schema::hasColumn('staff_profiles', 'contract_ends_at')) {
                $table->date('contract_ends_at')->nullable();
            }
        });

        if (! Schema::hasTable('staff_attendances')) {
            Schema::create('staff_attendances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->date('attendance_date');
                $table->string('status', 20);
                $table->time('check_in_at')->nullable();
                $table->time('check_out_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['staff_profile_id', 'attendance_date'], 'staff_attendance_staff_date_unique');
                $table->index(['branch_id', 'attendance_date', 'status']);
            });
        }

        if (! Schema::hasTable('staff_leave_requests')) {
            Schema::create('staff_leave_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('leave_type', 40);
                $table->date('starts_on');
                $table->date('ends_on');
                $table->text('reason');
                $table->string('status', 20)->default('pending');
                $table->text('review_note')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['branch_id', 'status', 'starts_on']);
                $table->index(['staff_profile_id', 'starts_on', 'ends_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leave_requests');
        Schema::dropIfExists('staff_attendances');
        Schema::table('staff_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('staff_profiles', 'user_id')) {
                $table->dropUnique(['user_id']);
                $table->dropConstrainedForeignId('user_id');
            }
            if (Schema::hasColumn('staff_profiles', 'employment_type')) $table->dropColumn('employment_type');
            if (Schema::hasColumn('staff_profiles', 'contract_ends_at')) $table->dropColumn('contract_ends_at');
        });
    }
};
