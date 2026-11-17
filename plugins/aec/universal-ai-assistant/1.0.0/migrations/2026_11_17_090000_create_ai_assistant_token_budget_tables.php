<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_assistant_daily_budgets')) {
            Schema::create('ai_assistant_daily_budgets', function (Blueprint $table): void {
                $table->id();
                $table->date('budget_date')->unique();
                $table->unsignedBigInteger('used_tokens')->default(0);
                $table->unsignedBigInteger('reserved_tokens')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_assistant_token_reservations')) {
            Schema::create('ai_assistant_token_reservations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->date('budget_date');
                $table->unsignedInteger('reserved_tokens');
                $table->string('status', 16)->default('reserved');
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->index(['budget_date', 'status', 'expires_at'], 'ai_assistant_reservations_expiry_idx');
            });
        }
    }

    public function down(): void
    {
        $hasReservations = Schema::hasTable('ai_assistant_token_reservations')
            && DB::table('ai_assistant_token_reservations')->exists();
        $hasUsage = Schema::hasTable('ai_assistant_daily_budgets')
            && DB::table('ai_assistant_daily_budgets')->exists();

        if ($hasReservations || $hasUsage) {
            throw new \\RuntimeException('AI token budget records exist; preserve them during rollback.');
        }

        Schema::dropIfExists('ai_assistant_token_reservations');
        Schema::dropIfExists('ai_assistant_daily_budgets');
    }
};
