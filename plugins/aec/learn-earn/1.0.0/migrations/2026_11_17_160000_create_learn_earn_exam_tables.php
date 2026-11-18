<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learn_earn_exams')) {
            Schema::create('learn_earn_exams', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 180);
                $table->string('slug', 200)->unique();
                $table->text('description')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->unsignedSmallInteger('duration_minutes');
                $table->unsignedInteger('pass_marks')->default(0);
                $table->unsignedSmallInteger('questions_per_attempt')->nullable();
                $table->unsignedSmallInteger('max_attempts')->default(1);
                $table->boolean('randomize_questions')->default(true);
                $table->string('access_type', 10)->default('free');
                $table->decimal('price', 10, 2)->default(0);
                $table->char('currency', 3)->default('BDT');
                $table->string('status', 20)->default('draft');
                $table->boolean('show_results')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'starts_at', 'ends_at']);
                $table->index(['access_type', 'status']);
            });
        }

        if (! Schema::hasTable('learn_earn_exam_questions')) {
            Schema::create('learn_earn_exam_questions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('exam_id')->constrained('learn_earn_exams')->cascadeOnDelete();
                $table->foreignId('source_question_id')->nullable()->constrained('learn_earn_questions')->nullOnDelete();
                $table->text('question');
                $table->json('options');
                $table->unsignedTinyInteger('correct_option');
                $table->unsignedSmallInteger('marks')->default(1);
                $table->unsignedSmallInteger('negative_marks')->default(0);
                $table->text('explanation')->nullable();
                $table->string('category_label', 120)->nullable();
                $table->unsignedSmallInteger('position');
                $table->timestamps();
                $table->unique(['exam_id', 'position']);
                $table->index(['exam_id', 'source_question_id']);
            });
        }
    }

    public function down(): void
    {
        $hasQuestions = Schema::hasTable('learn_earn_exam_questions')
            && DB::table('learn_earn_exam_questions')->exists();
        $hasExams = Schema::hasTable('learn_earn_exams')
            && DB::table('learn_earn_exams')->exists();

        if ($hasQuestions || $hasExams) {
            throw new \RuntimeException('Learn & Earn exams or exam snapshots exist; preserve them during plugin migration rollback.');
        }

        Schema::dropIfExists('learn_earn_exam_questions');
        Schema::dropIfExists('learn_earn_exams');
    }
};
