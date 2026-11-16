<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_assistant_settings') && ! Schema::hasColumn('ai_assistant_settings', 'role_data_access')) {
            Schema::table('ai_assistant_settings', function (Blueprint $table): void {
                $table->json('role_data_access')->nullable()->after('role_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_assistant_settings') && Schema::hasColumn('ai_assistant_settings', 'role_data_access')) {
            if (DB::table('ai_assistant_settings')->whereNotNull('role_data_access')->exists()) {
                throw new \RuntimeException('Export or explicitly reset role-scoped AI grants before rolling back this migration.');
            }
            Schema::table('ai_assistant_settings', function (Blueprint $table): void {
                $table->dropColumn('role_data_access');
            });
        }
    }
};
