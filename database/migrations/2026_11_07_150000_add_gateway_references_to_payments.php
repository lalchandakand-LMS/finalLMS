<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->string('gateway_transaction_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway_payment_id']);
            $table->dropConstrainedForeignId('batch_id');
            $table->dropColumn('gateway_transaction_id');
        });
    }
};
