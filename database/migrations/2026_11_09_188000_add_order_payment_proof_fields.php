<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_sender', 60)->nullable()->after('payment_reference');
            $table->string('payment_proof_path', 500)->nullable()->after('payment_sender');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_sender','payment_proof_path']);
        });
    }
};
