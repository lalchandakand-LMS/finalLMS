<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_content_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('short_description', 500)->nullable();
            $table->longText('long_description');
            $table->string('video_url', 500)->nullable();
            $table->string('seo_title', 180)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('course_content_post_category', function (Blueprint $table) {
            $table->foreignId('course_content_post_id')->constrained('course_content_posts')->cascadeOnDelete();
            $table->foreignId('course_category_id')->constrained('course_categories')->cascadeOnDelete();
            $table->primary(['course_content_post_id', 'course_category_id']);
            $table->index('course_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_content_post_category');
        Schema::dropIfExists('course_content_posts');
    }
};
