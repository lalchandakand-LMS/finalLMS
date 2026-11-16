<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_assistant_settings')) {
            Schema::create('ai_assistant_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->string('provider', 40)->default('openai-compatible');
                $table->string('model', 120)->default('gpt-4o-mini');
                $table->string('base_url', 255)->default('https://api.openai.com/v1');
                $table->text('api_key_encrypted')->nullable();
                $table->decimal('temperature', 3, 2)->default(0.70);
                $table->unsignedInteger('max_tokens')->default(1200);
                $table->boolean('global_enabled')->default(false);
                $table->json('role_enabled');
                $table->string('data_access_mode', 32)->default('general_only');
                $table->unsignedInteger('daily_request_limit')->default(20);
                $table->unsignedInteger('daily_token_budget')->default(50000);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_assistant_conversations')) {
            Schema::create('ai_assistant_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('assistant_role', 32);
                $table->string('title', 120);
                $table->timestamps();
                $table->index(['user_id', 'assistant_role', 'updated_at'], 'ai_assistant_conversations_owner_idx');
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
                $table->index(['message_role', 'created_at'], 'ai_assistant_messages_budget_idx');
            });
        }
    }

    public function down(): void
    {
        $hasHistory = Schema::hasTable('ai_assistant_conversations') && DB::table('ai_assistant_conversations')->exists();
        $hasMessages = Schema::hasTable('ai_assistant_messages') && DB::table('ai_assistant_messages')->exists();
        $hasSettings = Schema::hasTable('ai_assistant_settings') && DB::table('ai_assistant_settings')->exists();

        if ($hasHistory || $hasMessages || $hasSettings) {
            throw new \RuntimeException('AI assistant data exists. Export or explicitly erase retained AI data before rolling back this migration.');
        }

        Schema::dropIfExists('ai_assistant_messages');
        Schema::dropIfExists('ai_assistant_conversations');
        Schema::dropIfExists('ai_assistant_settings');
    }
};
