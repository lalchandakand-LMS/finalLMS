<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_assistant_budget_days')) {
            Schema::create('ai_assistant_budget_days', function (Blueprint $table): void {
                $table->date('budget_date')->primary();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_assistant_token_reservations')) {
            Schema::create('ai_assistant_token_reservations', function (Blueprint $table): void {
                $table->id();
                $table->date('budget_date');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('conversation_id')
                    ->constrained('ai_assistant_conversations')
                    ->cascadeOnDelete();
                $table->string('assistant_role', 32);
                $table->string('provider', 40);
                $table->string('model', 120);
                $table->unsignedInteger('prompt_tokens');
                $table->unsignedInteger('completion_limit');
                $table->string('status', 16)->default('pending');
                $table->unsignedInteger('actual_tokens')->nullable();
                $table->boolean('prompt_persisted')->default(false);
                $table->timestamp('expires_at')->index();
                $table->timestamps();
                $table->index(['budget_date', 'status'], 'ai_assistant_reservations_budget_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_token_reservations');
        Schema::dropIfExists('ai_assistant_budget_days');
    }
};
