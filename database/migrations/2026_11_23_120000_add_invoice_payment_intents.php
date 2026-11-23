<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->after('course_id')->constrained('invoices')->nullOnDelete();
            $table->index(['invoice_id', 'status'], 'payments_invoice_status_idx');
        });
    }

    public function down(): void
    {
        if (DB::table('payments')->whereNotNull('invoice_id')->exists()) {
            throw new RuntimeException('Refusing to remove invoice payment intent links; financial history must be preserved.');
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_invoice_status_idx');
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
