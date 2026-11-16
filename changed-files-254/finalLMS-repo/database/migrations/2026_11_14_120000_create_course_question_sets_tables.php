<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_sets', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('question_set_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_set_id')->constrained('question_sets')->cascadeOnDelete();
            $table->foreignId('source_question_id')->nullable()->constrained('question_bank_questions')->nullOnDelete();
            $table->string('type', 20);
            $table->text('question');
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('correct_option')->nullable();
            $table->text('correct_answer')->nullable();
            $table->text('explanation')->nullable();
            $table->string('difficulty', 16)->default('medium');
            $table->json('tags')->nullable();
            $table->unsignedSmallInteger('marks')->default(1);
            $table->unsignedInteger('position')->default(10);
            $table->timestamps();
            $table->index(['question_set_id', 'position']);
            $table->index(['type', 'difficulty']);
        });

        Schema::create('course_question_set', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('question_set_id')->constrained('question_sets')->cascadeOnDelete();
            $table->string('section_title', 120)->nullable();
            $table->unsignedInteger('position')->default(10);
            $table->unsignedSmallInteger('max_attempts')->nullable();
            $table->boolean('randomize_questions')->default(true);
            $table->timestamp('available_from')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'question_set_id'], 'course_question_set_unique');
            $table->index(['course_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_question_set');
        Schema::dropIfExists('question_set_questions');
        Schema::dropIfExists('question_sets');
    }
};
