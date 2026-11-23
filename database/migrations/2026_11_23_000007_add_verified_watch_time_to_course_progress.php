<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_content_progress', function (Blueprint $table): void {
            if (! Schema::hasColumn('course_content_progress', 'watched_seconds')) {
                $table->unsignedInteger('watched_seconds')->default(0);
            }
            if (! Schema::hasColumn('course_content_progress', 'last_progress_ping_at')) {
                $table->timestamp('last_progress_ping_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_content_progress', function (Blueprint $table): void {
            if (Schema::hasColumn('course_content_progress', 'last_progress_ping_at')) $table->dropColumn('last_progress_ping_at');
            if (Schema::hasColumn('course_content_progress', 'watched_seconds')) $table->dropColumn('watched_seconds');
        });
    }
};
