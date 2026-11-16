<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('short_description', 500)->nullable()->after('description');
            $table->json('tags')->nullable()->after('short_description');
            $table->string('seo_title', 255)->nullable()->after('tags');
            $table->string('seo_description', 320)->nullable()->after('seo_title');
            $table->text('learning_outcomes')->nullable()->after('seo_description');
            $table->text('requirements')->nullable()->after('learning_outcomes');
            $table->string('level', 40)->nullable()->after('requirements');
            $table->string('language', 40)->nullable()->after('level');
            $table->unsignedInteger('duration_minutes')->nullable()->after('language');
        });

        Schema::create('course_category_course', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('course_category_id')->constrained('course_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_id', 'course_category_id'], 'course_category_course_unique');
            $table->index('course_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_category_course');

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn([
                'short_description',
                'tags',
                'seo_title',
                'seo_description',
                'learning_outcomes',
                'requirements',
                'level',
                'language',
                'duration_minutes',
            ]);
        });
    }
};
