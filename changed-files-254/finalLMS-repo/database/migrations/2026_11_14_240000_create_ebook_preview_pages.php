<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ebook_preview_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedTinyInteger('page_number');
            $table->string('file_path', 500);
            $table->string('alt_text', 180)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'page_number']);
            $table->index(['product_id', 'is_active', 'page_number'], 'ebook_preview_visibility_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_preview_pages');
    }
};
