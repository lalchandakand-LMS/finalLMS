<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aec_prep_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('track', 20)->index();
            $table->string('subject', 100)->index();
            $table->string('topic', 120)->nullable()->index();
            $table->unsignedSmallInteger('source_year')->nullable()->index();
            $table->string('source_name', 120)->nullable();
            $table->string('type', 20)->default('mcq')->index();
            $table->string('difficulty', 16)->default('medium')->index();
            $table->longText('question');
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('correct_option')->nullable();
            $table->longText('correct_answer')->nullable();
            $table->longText('explanation')->nullable();
            $table->unsignedSmallInteger('marks')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['track', 'subject', 'source_year'], 'aec_prep_track_subject_year_idx');
        });

        Schema::create('aec_prep_question_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('track', 20)->index();
            $table->string('title', 180);
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('status', 16)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('aec_prep_question_set_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_set_id')->constrained('aec_prep_question_sets')->cascadeOnDelete();
            $table->unsignedBigInteger('source_question_id')->nullable()->index();
            $table->string('type', 20);
            $table->string('subject', 100);
            $table->string('topic', 120)->nullable();
            $table->longText('question');
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('correct_option')->nullable();
            $table->longText('correct_answer')->nullable();
            $table->longText('explanation')->nullable();
            $table->unsignedSmallInteger('marks')->default(1);
            $table->unsignedInteger('position')->default(10);
            $table->timestamps();
            $table->index(['question_set_id', 'position'], 'aec_prep_set_position_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('aec_prep_question_sets') && DB::table('aec_prep_question_sets')->exists()) {
            throw new RuntimeException('Exam papers exist. Export or explicitly remove exam papers before uninstalling this plugin.');
        }
        if (Schema::hasTable('aec_prep_question_set_items') && DB::table('aec_prep_question_set_items')->exists()) {
            throw new RuntimeException('Exam question sets exist. Export or explicitly remove exam sets before uninstalling this plugin.');
        }
        if (Schema::hasTable('aec_prep_questions') && DB::table('aec_prep_questions')->exists()) {
            throw new RuntimeException('Question bank records exist. Export or explicitly remove questions before uninstalling this plugin.');
        }
        Schema::dropIfExists('aec_prep_question_set_items');
        Schema::dropIfExists('aec_prep_question_sets');
        Schema::dropIfExists('aec_prep_questions');
    }
};
