<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('assignments', 'question_set_id')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->foreignId('question_set_id')
                    ->nullable()
                    ->after('teacher_id')
                    ->constrained('question_sets')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('assignment_submissions', 'question_answers')) {
            Schema::table('assignment_submissions', function (Blueprint $table): void {
                $table->json('question_answers')->nullable()->after('answer');
            });
        }
    }

    public function down(): void
    {
        $hasQuestionSetAssignments = Schema::hasColumn('assignments', 'question_set_id')
            && DB::table('assignments')->whereNotNull('question_set_id')->exists();
        $hasQuestionAnswers = Schema::hasColumn('assignment_submissions', 'question_answers')
            && DB::table('assignment_submissions')->whereNotNull('question_answers')->exists();

        if ($hasQuestionSetAssignments || $hasQuestionAnswers) {
            throw new RuntimeException('Question-set homework or learner answers exist; preserve this data before rolling back the assignment extension.');
        }

        if (Schema::hasColumn('assignments', 'question_set_id')) {
            Schema::table('assignments', function (Blueprint $table): void {
                $table->dropForeign(['question_set_id']);
                $table->dropColumn('question_set_id');
            });
        }

        if (Schema::hasColumn('assignment_submissions', 'question_answers')) {
            Schema::table('assignment_submissions', function (Blueprint $table): void {
                $table->dropColumn('question_answers');
            });
        }
    }
};
