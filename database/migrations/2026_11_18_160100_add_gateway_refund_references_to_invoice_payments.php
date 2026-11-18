<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->string('gateway_provider', 40)->nullable()->after('method');
            $table->string('gateway_payment_id', 120)->nullable()->after('gateway_provider')->index();
            $table->string('gateway_transaction_id', 120)->nullable()->after('gateway_payment_id')->index();
        });

        Schema::table('invoice_refunds', function (Blueprint $table) {
            $table->string('gateway_refund_id', 120)->nullable()->after('reference')->index();
            $table->string('gateway_status', 40)->nullable()->after('gateway_refund_id');
            $table->text('failure_reason')->nullable()->after('gateway_status');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_refunds', function (Blueprint $table) {
            $table->dropColumn(['gateway_refund_id', 'gateway_status', 'failure_reason']);
        });
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropIndex(['gateway_payment_id']);
            $table->dropIndex(['gateway_transaction_id']);
            $table->dropColumn(['gateway_provider', 'gateway_payment_id', 'gateway_transaction_id']);
        });
    }
};
