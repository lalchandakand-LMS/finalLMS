<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table): void {
            $table->string('fee_calculation_mode', 20)->default('fixed');
            $table->decimal('per_class_fee', 10, 2)->nullable();
        });

        Schema::create('attendance_fee_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('billing_period', 7);
            $table->unsignedSmallInteger('attended_classes');
            $table->decimal('per_class_fee', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['batch_id', 'student_id', 'billing_period'], 'attendance_fee_batch_student_period_unique');
            $table->unique('invoice_id');
            $table->index(['billing_period', 'batch_id']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('attendance_fee_periods')->exists()) {
            throw new RuntimeException('Attendance fee invoice history exists; retain it for financial audit history.');
        }

        Schema::dropIfExists('attendance_fee_periods');
        Schema::table('batches', function (Blueprint $table): void {
            $table->dropColumn(['fee_calculation_mode', 'per_class_fee']);
        });
    }
};
