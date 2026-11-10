<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_update_preparations', function (Blueprint $table) {
            $table->char('migration_set_sha256', 64)->after('manifest_sha256');
            $table->string('release_path', 255)->nullable()->after('package_path');
            $table->timestamp('staged_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('system_update_preparations', function (Blueprint $table) {
            $table->dropColumn(['migration_set_sha256', 'release_path', 'staged_at']);
        });
    }
};
