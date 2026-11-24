<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('is_active');
            $table->boolean('registration_open')->default(true)->after('status');
            $table->index(['status', 'registration_open'], 'branches_status_registration_idx');
        });

        // Keep the legacy is_active flag consistent for existing branch-aware features.
        DB::table('branches')->where('is_active', false)->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropIndex('branches_status_registration_idx');
            $table->dropColumn(['status', 'registration_open']);
        });
    }
};
