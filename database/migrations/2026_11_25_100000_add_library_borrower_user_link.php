<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('library_loans') && ! Schema::hasColumn('library_loans', 'borrower_user_id')) {
            Schema::table('library_loans', function (Blueprint $table): void {
                $table->foreignId('borrower_user_id')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
                $table->index(['borrower_user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('library_loans') && Schema::hasColumn('library_loans', 'borrower_user_id')) {
            Schema::table('library_loans', function (Blueprint $table): void {
                $table->dropForeign(['borrower_user_id']);
                $table->dropIndex(['borrower_user_id', 'status']);
                $table->dropColumn('borrower_user_id');
            });
        }
    }
};
