<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 30)->default('public');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('alt_text', 255)->nullable();
            $table->timestamps();
            $table->index(['mime_type', 'created_at']);
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('media_assets');
    }
};
