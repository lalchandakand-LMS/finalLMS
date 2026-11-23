<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_carousels')) {
            Schema::create('website_carousels', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 160);
                $table->string('slug', 180)->unique();
                $table->string('source_type', 24)->default('manual')->index();
                $table->unsignedTinyInteger('item_limit')->default(8);
                $table->unsignedTinyInteger('desktop_cards')->default(3);
                $table->unsignedTinyInteger('mobile_cards')->default(1);
                $table->boolean('autoplay')->default(false);
                $table->unsignedSmallInteger('interval_ms')->default(5000);
                $table->boolean('show_arrows')->default(true);
                $table->boolean('show_dots')->default(true);
                $table->string('status', 16)->default('draft')->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamp('starts_at')->nullable()->index();
                $table->timestamp('ends_at')->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('website_carousel_items')) {
            Schema::create('website_carousel_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('carousel_id')->constrained('website_carousels')->cascadeOnDelete();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->string('image_url', 2048)->nullable();
                $table->string('link_url', 2048)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['carousel_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_carousel_items');
        Schema::dropIfExists('website_carousels');
    }
};