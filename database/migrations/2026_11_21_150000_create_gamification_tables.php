<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gamification_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('leaderboard_opt_in')->default(false);
            $table->string('display_name', 40)->nullable();
            $table->boolean('show_streak')->default(false);
            $table->date('current_streak_date')->nullable();
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->timestamps();
        });

        Schema::create('gamification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 64);
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('xp');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['user_id', 'event_key', 'source_type', 'source_id'], 'gamification_event_idempotency');
            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('gamification_badges', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->string('description', 500);
            $table->string('icon', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gamification_user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained('gamification_badges')->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_user_badges');
        Schema::dropIfExists('gamification_badges');
        Schema::dropIfExists('gamification_events');
        Schema::dropIfExists('gamification_profiles');
    }
};
