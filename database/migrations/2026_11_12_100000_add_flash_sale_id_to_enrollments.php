<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('flash_sale_id')->nullable()->after('batch_id')
                ->constrained('flash_sales')->nullOnDelete();
            $table->index(['flash_sale_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['flash_sale_id', 'status']);
            $table->dropConstrainedForeignId('flash_sale_id');
        });
    }
};
