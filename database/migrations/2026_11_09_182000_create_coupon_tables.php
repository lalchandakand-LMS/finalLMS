<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('discount_type', 16);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('minimum_order', 12, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active','starts_at','ends_at']);
        });
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamps();
            $table->unique(['coupon_id','order_id']);
            $table->index(['coupon_id','user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
