<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        // The newest migration guards the entire plugin data set. Migration
        // rollback is sequential, so this prevents newer tables/columns being
        // removed before an older migration discovers that exam content exists.
        foreach ([
            'learn_earn_exam_entitlements',
            'learn_earn_exam_answers',
            'learn_earn_attempt_questions',
            'learn_earn_exam_attempts',
            'learn_earn_exam_questions',
            'learn_earn_exams',
            'learn_earn_questions',
            'learn_earn_categories',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new \RuntimeException('Learn & Earn content or payment history exists; preserve the complete plugin schema during uninstall.');
            }
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'learn_earn_exam_id')
            && DB::table('payments')->whereNotNull('learn_earn_exam_id')->exists()) {
            throw new \RuntimeException('Learn & Earn payment records exist; preserve them during plugin migration rollback.');
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
