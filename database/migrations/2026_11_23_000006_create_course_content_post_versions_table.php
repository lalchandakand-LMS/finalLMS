<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_content_post_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_content_post_id')->constrained('course_content_posts')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_type', 30)->default('update');
            $table->timestamps();
            $table->unique(['course_content_post_id', 'version_number'], 'content_post_version_unique');
            $table->index(['course_content_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_content_post_versions');
    }
};
