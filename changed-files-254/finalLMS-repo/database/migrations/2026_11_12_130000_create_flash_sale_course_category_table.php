<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sale_course_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('course_category_id')->constrained('course_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['flash_sale_id', 'course_category_id'], 'flash_sale_category_unique');
            $table->index('course_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_course_category');
    }
};
