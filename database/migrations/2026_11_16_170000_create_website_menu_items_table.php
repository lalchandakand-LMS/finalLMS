<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 80);
            $table->string('url', 500);
            $table->foreignId('parent_id')->nullable()->constrained('website_menu_items')->nullOnDelete();
            $table->boolean('target_blank')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_menu_items');
    }
};
