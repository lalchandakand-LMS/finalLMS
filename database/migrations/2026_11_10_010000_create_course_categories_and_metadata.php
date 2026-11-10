<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('course_category_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_id', 'course_category_id'], 'course_category_course_unique');
        });

        Schema::create('blog_post_course_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blog_post_id', 'course_category_id'], 'blog_post_course_category_unique');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('short_description', 500)->nullable()->after('description');
            $table->json('tags')->nullable()->after('short_description');
            $table->string('seo_title')->nullable()->after('tags');
            $table->string('seo_description', 320)->nullable()->after('seo_title');
            $table->text('learning_outcomes')->nullable()->after('seo_description');
            $table->text('requirements')->nullable()->after('learning_outcomes');
            $table->string('level', 40)->nullable()->after('requirements');
            $table->string('language', 40)->nullable()->after('level');
            $table->unsignedInteger('duration_minutes')->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'short_description', 'tags', 'seo_title', 'seo_description',
                'learning_outcomes', 'requirements', 'level', 'language', 'duration_minutes',
            ]);
        });
        Schema::dropIfExists('blog_post_course_category');
        Schema::dropIfExists('course_category_course');
        Schema::dropIfExists('course_categories');
    }
};
