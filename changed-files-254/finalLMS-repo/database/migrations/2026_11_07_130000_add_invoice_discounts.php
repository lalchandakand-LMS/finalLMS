<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->nullable()->after('amount');
            $table->string('discount_type', 20)->nullable()->after('subtotal');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'discount_type', 'discount_value']);
        });
    }
};