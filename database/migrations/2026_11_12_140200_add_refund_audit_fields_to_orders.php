<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('completed_at');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
            $table->text('refund_note')->nullable()->after('refunded_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn(['refunded_at', 'refund_note']);
        });
    }
};
