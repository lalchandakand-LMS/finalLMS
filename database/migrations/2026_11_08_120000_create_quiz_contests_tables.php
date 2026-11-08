<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quiz_contests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('prize_description', 500);
            $table->unsignedSmallInteger('winner_count')->default(1);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('drawn_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('quiz_contest_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_contest_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->json('options');
            $table->unsignedTinyInteger('correct_option');
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('quiz_contest_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_contest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->unsignedSmallInteger('score')->default(0);
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['quiz_contest_id', 'user_id']);
        });

        Schema::create('quiz_contest_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_contest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_contest_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->string('prize_snapshot', 500);
            $table->foreignId('drawn_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('drawn_at');
            $table->timestamps();
            $table->unique(['quiz_contest_id', 'rank']);
            $table->unique('quiz_contest_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_contest_winners');
        Schema::dropIfExists('quiz_contest_entries');
        Schema::dropIfExists('quiz_contest_questions');
        Schema::dropIfExists('quiz_contests');
    }
};
