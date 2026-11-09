<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('flash_sale_id')->nullable()->after('product_id')->constrained('flash_sales')->nullOnDelete();
            $table->index(['flash_sale_id','created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['flash_sale_id','created_at']);
            $table->dropConstrainedForeignId('flash_sale_id');
        });
    }
};
