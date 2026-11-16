<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_curriculum_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('course_content_post_id')->constrained('course_content_posts')->cascadeOnDelete();
            $table->string('section_title', 120)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_preview')->default(false);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'course_content_post_id'], 'course_curriculum_course_post_unique');
            $table->index(['course_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_curriculum_items');
    }
};
