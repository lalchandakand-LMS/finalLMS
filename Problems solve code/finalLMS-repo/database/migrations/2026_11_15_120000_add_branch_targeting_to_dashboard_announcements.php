<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_announcements', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('audience')->constrained('branches')->nullOnDelete();
            $table->index(['branch_id', 'is_active', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_announcements', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'is_active', 'audience']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
