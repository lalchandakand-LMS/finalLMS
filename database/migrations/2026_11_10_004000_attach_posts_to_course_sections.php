<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_section_blog_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('attached_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->unique(['course_section_id','blog_post_id']);
            $table->index(['course_section_id','position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_section_blog_post');
    }
};
