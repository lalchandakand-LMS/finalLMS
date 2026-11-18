<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learn_earn_exam_attempts')) {
            Schema::create('learn_earn_exam_attempts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('exam_id')->constrained('learn_earn_exams')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedSmallInteger('attempt_number');
                $table->string('status', 20)->default('in_progress');
                $table->dateTime('started_at');
                $table->dateTime('ends_at');
                $table->dateTime('submitted_at')->nullable();
                $table->unsignedInteger('score')->default(0);
                $table->unsignedInteger('max_score')->default(0);
                $table->timestamps();
                $table->unique(['exam_id', 'student_id', 'attempt_number'], 'learn_earn_attempt_number_unique');
                $table->index(['student_id', 'status', 'created_at']);
                $table->index(['exam_id', 'status']);
            });
        }

        if (! Schema::hasTable('learn_earn_attempt_questions')) {
            Schema::create('learn_earn_attempt_questions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('attempt_id')->constrained('learn_earn_exam_attempts')->cascadeOnDelete();
                $table->foreignId('exam_question_id')->constrained('learn_earn_exam_questions')->restrictOnDelete();
                $table->unsignedSmallInteger('position');
                $table->timestamps();
                $table->unique(['attempt_id', 'exam_question_id'], 'learn_earn_attempt_question_unique');
                $table->unique(['attempt_id', 'position'], 'learn_earn_attempt_position_unique');
            });
        }

        if (! Schema::hasTable('learn_earn_exam_answers')) {
            Schema::create('learn_earn_exam_answers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('attempt_id')->constrained('learn_earn_exam_attempts')->cascadeOnDelete();
                $table->foreignId('exam_question_id')->constrained('learn_earn_exam_questions')->restrictOnDelete();
                $table->unsignedTinyInteger('selected_option')->nullable();
                $table->boolean('is_correct')->default(false);
                $table->integer('marks_awarded')->default(0);
                $table->timestamps();
                $table->unique(['attempt_id', 'exam_question_id'], 'learn_earn_answer_unique');
                $table->index(['attempt_id', 'is_correct']);
            });
        }
    }

    public function down(): void
    {
        foreach (['learn_earn_exam_answers', 'learn_earn_attempt_questions', 'learn_earn_exam_attempts'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new \RuntimeException('Learn & Earn attempts or answers exist; preserve them during plugin migration rollback.');
            }
        }

        Schema::dropIfExists('learn_earn_exam_answers');
        Schema::dropIfExists('learn_earn_attempt_questions');
        Schema::dropIfExists('learn_earn_exam_attempts');
    }
};
