<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('category', 120)->nullable()->after('excerpt');
            $table->json('tags')->nullable()->after('category');
            $table->string('video_url', 2048)->nullable()->after('body');
            $table->index(['category','status']);
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex(['category','status']);
            $table->dropColumn(['category','tags','video_url']);
        });
    }
};
