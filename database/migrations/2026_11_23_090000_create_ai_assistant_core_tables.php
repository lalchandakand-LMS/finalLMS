<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_assistant_settings')) {
            Schema::create('ai_assistant_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->string('provider', 64)->default('openai-compatible');
                $table->string('model', 120)->default('gpt-4o-mini');
                $table->string('base_url', 255)->default('https://api.openai.com/v1');
                $table->text('api_key_encrypted')->nullable();
                $table->decimal('temperature', 3, 2)->default(0.70);
                $table->unsignedInteger('max_tokens')->default(1200);
                $table->boolean('global_enabled')->default(false);
                $table->json('role_enabled');
                $table->json('role_data_access');
                $table->string('data_access_mode', 32)->default('general_only');
                $table->unsignedInteger('daily_request_limit')->default(20);
                $table->unsignedInteger('daily_token_budget')->default(50000);
                $table->unsignedSmallInteger('retention_days')->default(365);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_assistant_conversations')) {
            Schema::create('ai_assistant_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('assistant_role', 32)->index();
                $table->string('title', 100);
                $table->timestamps();
                $table->index(['user_id', 'assistant_role', 'updated_at'], 'ai_conversation_owner_role_updated');
            });
        }

        if (! Schema::hasTable('ai_assistant_messages')) {
            Schema::create('ai_assistant_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained('ai_assistant_conversations')->cascadeOnDelete();
                $table->string('message_role', 16);
                $table->longText('content');
                $table->unsignedInteger('token_count')->nullable();
                $table->string('model', 120)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['message_role', 'created_at'], 'ai_message_role_created');
            });
        }

        if (! Schema::hasTable('ai_assistant_budget_days')) {
            Schema::create('ai_assistant_budget_days', function (Blueprint $table): void {
                $table->date('budget_date')->primary();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_assistant_token_reservations')) {
            Schema::create('ai_assistant_token_reservations', function (Blueprint $table): void {
                $table->id();
                $table->date('budget_date')->index();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('conversation_id')->constrained('ai_assistant_conversations')->cascadeOnDelete();
                $table->string('assistant_role', 32);
                $table->string('provider', 64);
                $table->string('model', 120);
                $table->unsignedInteger('prompt_tokens');
                $table->unsignedInteger('completion_limit');
                $table->string('status', 20)->default('pending')->index();
                $table->unsignedInteger('actual_tokens')->nullable();
                $table->boolean('prompt_persisted')->default(false);
                $table->timestamp('expires_at')->index();
                $table->timestamps();
                $table->index(['budget_date', 'user_id', 'status'], 'ai_reservation_daily_user_status');
            });
        }

        if (! Schema::hasTable('ai_assistant_audit_events')) {
            Schema::create('ai_assistant_audit_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->unsignedBigInteger('reservation_id')->nullable()->index();
                $table->string('assistant_role', 32);
                $table->string('provider', 64);
                $table->string('model', 120);
                $table->string('event', 80)->index();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_audit_events');
        Schema::dropIfExists('ai_assistant_token_reservations');
        Schema::dropIfExists('ai_assistant_budget_days');
        Schema::dropIfExists('ai_assistant_messages');
        Schema::dropIfExists('ai_assistant_conversations');
        Schema::dropIfExists('ai_assistant_settings');
    }
};