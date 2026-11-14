<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_question_set', function (Blueprint $table) {
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->unsignedSmallInteger('questions_per_attempt')->nullable();
        });

        Schema::create('question_set_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_set_id')->constrained('question_sets')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 24)->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedSmallInteger('auto_score')->default(0);
            $table->unsignedSmallInteger('manual_score')->default(0);
            $table->unsignedSmallInteger('max_score')->default(0);
            $table->unsignedSmallInteger('final_score')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->timestamps();
            $table->unique(['question_set_id', 'course_id', 'student_id', 'attempt_number'], 'question_set_attempt_number_unique');
            $table->index(['student_id', 'status']);
            $table->index(['course_id', 'question_set_id', 'status']);
        });

        Schema::create('question_set_attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_set_attempt_id')->constrained('question_set_attempts')->cascadeOnDelete();
            $table->foreignId('question_set_question_id')->nullable()->constrained('question_set_questions')->nullOnDelete();
            $table->string('type', 20);
            $table->text('question');
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('correct_option')->nullable();
            $table->text('correct_answer')->nullable();
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('marks')->default(1);
            $table->unsignedInteger('position')->default(10);
            $table->unsignedTinyInteger('selected_option')->nullable();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedSmallInteger('marks_awarded')->default(0);
            $table->text('manual_feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->index(['question_set_attempt_id', 'position'], 'question_set_attempt_questions_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_set_attempt_questions');
        Schema::dropIfExists('question_set_attempts');
        Schema::table('course_question_set', function (Blueprint $table) {
            $table->dropColumn(['time_limit_minutes', 'questions_per_attempt']);
        });
    }
};
