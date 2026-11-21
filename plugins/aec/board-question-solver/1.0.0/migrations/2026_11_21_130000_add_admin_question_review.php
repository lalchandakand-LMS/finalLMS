<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('board_question_solutions')) {
            Schema::table('board_question_solutions', function (Blueprint $table): void {
                if (! Schema::hasColumn('board_question_solutions', 'review_status')) {
                    $table->string('review_status', 16)->default('pending')->index();
                }
                if (! Schema::hasColumn('board_question_solutions', 'reviewed_by')) {
                    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('board_question_solutions', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable();
                }
                if (! Schema::hasColumn('board_question_solutions', 'admin_notes')) {
                    $table->text('admin_notes')->nullable();
                }
                if (! Schema::hasColumn('board_question_solutions', 'question_set_id')) {
                    $table->foreignId('question_set_id')->nullable()->constrained('question_sets')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('board_question_solutions')) {
            return;
        }

        Schema::table('board_question_solutions', function (Blueprint $table): void {
            if (Schema::hasColumn('board_question_solutions', 'question_set_id')) {
                $table->dropConstrainedForeignId('question_set_id');
            }
            if (Schema::hasColumn('board_question_solutions', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            foreach (['review_status', 'reviewed_at', 'admin_notes'] as $column) {
                if (Schema::hasColumn('board_question_solutions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
