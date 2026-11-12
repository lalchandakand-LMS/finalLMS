<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->string('status', 16)->default('pending')->after('discount_amount');
            $table->index(['coupon_id', 'status', 'user_id'], 'coupon_redemptions_status_user_index');
        });
    }

    public function down(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->dropIndex('coupon_redemptions_status_user_index');
            $table->dropColumn('status');
        });
    }
};
