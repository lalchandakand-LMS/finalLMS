<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('library_loan_id')->nullable()->unique()->after('batch_id')
                ->constrained('library_loans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['library_loan_id']);
            $table->dropConstrainedForeignId('library_loan_id');
        });
    }
};
