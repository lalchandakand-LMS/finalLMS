<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_assistant_settings') && ! Schema::hasColumn('ai_assistant_settings', 'retention_days')) {
            Schema::table('ai_assistant_settings', function (Blueprint $table): void {
                $table->unsignedSmallInteger('retention_days')->default(365);
            });
        }
    }

    public function down(): void
    {
        $hasHistory = Schema::hasTable('ai_assistant_conversations') && DB::table('ai_assistant_conversations')->exists();
        $hasMessages = Schema::hasTable('ai_assistant_messages') && DB::table('ai_assistant_messages')->exists();
        if ($hasHistory || $hasMessages) {
            throw new RuntimeException('AI assistant history exists. Export or explicitly erase retained AI data before rolling back retention settings.');
        }

        if (Schema::hasTable('ai_assistant_settings') && Schema::hasColumn('ai_assistant_settings', 'retention_days')) {
            Schema::table('ai_assistant_settings', function (Blueprint $table): void {
                $table->dropColumn('retention_days');
            });
        }
    }
};