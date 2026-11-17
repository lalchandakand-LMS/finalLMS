<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_assistant_user_daily_requests')) {
            Schema::create('ai_assistant_user_daily_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('request_date');
                $table->unsignedInteger('request_count')->default(0);
                $table->timestamps();
                $table->unique(['user_id', 'request_date'], 'ai_assistant_user_daily_requests_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_assistant_user_daily_requests')
            && DB::table('ai_assistant_user_daily_requests')->exists()) {
            throw new \\RuntimeException('AI request quota records exist; preserve them during rollback.');
        }

        Schema::dropIfExists('ai_assistant_user_daily_requests');
    }
};
