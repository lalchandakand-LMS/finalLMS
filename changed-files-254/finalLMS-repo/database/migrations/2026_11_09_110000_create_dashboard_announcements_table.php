<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dashboard_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->string('content_type', 20)->default('custom')->index();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->nullOnDelete();
            $table->foreignId('image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('button_label', 60)->nullable();
            $table->string('button_url', 500)->nullable();
            $table->string('audience', 20)->default('all')->index();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_announcements');
    }
};
