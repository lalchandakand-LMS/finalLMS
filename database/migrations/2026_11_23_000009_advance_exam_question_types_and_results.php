<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->string('type', 24)->default('mcq')->after('question');
            $table->text('correct_answer')->nullable()->after('correct_option');
            $table->text('explanation')->nullable();
            $table->json('grading_rubric')->nullable();
        });

        Schema::table('question_bank_questions', function (Blueprint $table): void {
            $table->string('type', 24)->default('mcq')->after('question');
            $table->text('correct_answer')->nullable()->after('correct_option');
            $table->text('explanation')->nullable();
            $table->json('grading_rubric')->nullable();
            $table->json('tags')->nullable();
        });

        Schema::table('exam_answers', function (Blueprint $table): void {
            $table->unsignedTinyInteger('selected_option')->nullable()->change();
            $table->longText('answer_text')->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->after('marks_awarded')->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();
        });

        Schema::table('exams', function (Blueprint $table): void {
            $table->json('grading_rules')->nullable();
            $table->string('result_status', 20)->default('draft');
            $table->dateTime('results_published_at')->nullable();
            $table->boolean('allow_result_review')->default(false);
            $table->dateTime('review_until')->nullable();
        });

        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->integer('score')->nullable()->change();
            $table->boolean('auto_submitted')->default(false);
            $table->dateTime('last_saved_at')->nullable();
            $table->dateTime('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('exam_attempts')->where('score', '<', 0)->exists()) {
            throw new RuntimeException('Cannot roll back custom negative total scores without preserving those results.');
        }

        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->unsignedInteger('score')->nullable()->change();
            $table->dropConstrainedForeignId('graded_by');
            $table->dropColumn(['auto_submitted', 'last_saved_at', 'graded_at']);
        });
        Schema::table('exams', function (Blueprint $table): void {
            $table->dropColumn(['grading_rules', 'result_status', 'results_published_at', 'allow_result_review', 'review_until']);
        });
        Schema::table('exam_answers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('graded_by');
            $table->dropColumn(['answer_text', 'teacher_feedback', 'graded_at']);
        });
        Schema::table('question_bank_questions', function (Blueprint $table): void {
            $table->dropColumn(['type', 'correct_answer', 'explanation', 'grading_rubric', 'tags']);
        });
        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->dropColumn(['type', 'correct_answer', 'explanation', 'grading_rubric']);
        });
    }
};
