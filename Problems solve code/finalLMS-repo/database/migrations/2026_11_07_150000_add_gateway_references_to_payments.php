<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_provider', 20)->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->string('gateway_transaction_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway_payment_id']);
            $table->dropUnique(['gateway_transaction_id']);
            $table->dropColumn(['gateway_provider', 'gateway_payment_id', 'gateway_transaction_id']);
        });
    }
};
