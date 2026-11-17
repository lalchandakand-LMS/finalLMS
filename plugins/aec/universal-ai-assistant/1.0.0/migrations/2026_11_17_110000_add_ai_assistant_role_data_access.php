<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_assistant_settings')
            && ! Schema::hasColumn('ai_assistant_settings', 'role_data_access')) {
            Schema::table('ai_assistant_settings', function (Blueprint $table): void {
                $table->json('role_data_access')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_assistant_settings')
            || ! Schema::hasColumn('ai_assistant_settings', 'role_data_access')) {
            return;
        }

        $hasActiveGrant = DB::table('ai_assistant_settings')->pluck('role_data_access')->contains(
            static function ($encoded): bool {
                $grants = json_decode((string) $encoded, true);
                return is_array($grants) && in_array(true, array_values($grants), true);
            }
        );
        if ($hasActiveGrant) {
            throw new RuntimeException('Per-role AI data-access grants exist. Export or explicitly clear these settings before rolling back.');
        }

        Schema::table('ai_assistant_settings', function (Blueprint $table): void {
            $table->dropColumn('role_data_access');
        });
    }
};
