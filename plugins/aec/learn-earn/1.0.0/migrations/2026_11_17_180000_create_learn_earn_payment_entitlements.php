<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'learn_earn_exam_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->foreignId('learn_earn_exam_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('learn_earn_exams')
                    ->restrictOnDelete();
                $table->index(['learn_earn_exam_id', 'user_id', 'status'], 'payments_learn_earn_exam_status_index');
            });
        }

        if (! Schema::hasTable('learn_earn_exam_entitlements')) {
            Schema::create('learn_earn_exam_entitlements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('exam_id')->constrained('learn_earn_exams')->restrictOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('payment_id')->unique()->constrained('payments')->restrictOnDelete();
                $table->string('status', 20)->default('active');
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->unique(['exam_id', 'student_id'], 'learn_earn_exam_student_entitlement_unique');
                $table->index(['student_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('learn_earn_exam_entitlements') && DB::table('learn_earn_exam_entitlements')->exists()) {
            throw new RuntimeException('Learn & Earn payment entitlements exist; preserve them during plugin migration rollback.');
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'learn_earn_exam_id')
            && DB::table('payments')->whereNotNull('learn_earn_exam_id')->exists()) {
            throw new RuntimeException('Learn & Earn payment records exist; preserve them during plugin migration rollback.');
        }

        Schema::dropIfExists('learn_earn_exam_entitlements');

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'learn_earn_exam_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropIndex('payments_learn_earn_exam_status_index');
                $table->dropConstrainedForeignId('learn_earn_exam_id');
            });
        }
    }
};
