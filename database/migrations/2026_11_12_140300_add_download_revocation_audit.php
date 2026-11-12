<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('download_permissions', function (Blueprint $table) {
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable()->after('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('download_permissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn('revocation_reason');
        });
    }
};
