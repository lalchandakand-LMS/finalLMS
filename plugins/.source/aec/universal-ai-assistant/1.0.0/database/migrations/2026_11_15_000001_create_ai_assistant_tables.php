<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('provider', 40)->default('openai');
            $table->string('model', 120)->default('');
            $table->text('api_key_encrypted')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->unsignedInteger('max_tokens')->default(1024);
            $table->unsignedBigInteger('monthly_token_budget')->nullable();
            $table->unsignedSmallInteger('retention_days')->default(365);
            $table->json('role_settings');
            $table->json('data_access');
            $table->timestamps();
        });

        Schema::create('ai_assistant_conversations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 40)->index();
            $table->string('title', 120)->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('ai_assistant_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('role', 20);
            $table->longText('content');
            $table->timestamps();
            $table->foreign('conversation_id')->references('id')->on('ai_assistant_conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ai_assistant_usage', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 40);
            $table->string('provider', 40);
            $table->string('model', 120);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_usage');
        Schema::dropIfExists('ai_assistant_messages');
        Schema::dropIfExists('ai_assistant_conversations');
        Schema::dropIfExists('ai_assistant_settings');
    }
};