<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('admin_permissions')->nullable()->after('branch_id');
        });

        // Preserve the exact scoped access existing staff already had.
        DB::table('users')->where('role', 'branch_manager')->update([
            'admin_permissions' => json_encode(['branch.batches', 'branch.students']),
        ]);
        DB::table('users')->where('role', 'accountant')->update([
            'admin_permissions' => json_encode(['finance.invoices', 'finance.refunds', 'finance.reports', 'finance.expenses']),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('admin_permissions');
        });
    }
};
