<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_content_progress', function (Blueprint $table): void {
            if (! Schema::hasColumn('course_content_progress', 'last_position_seconds')) $table->unsignedInteger('last_position_seconds')->default(0);
            if (! Schema::hasColumn('course_content_progress', 'duration_seconds')) $table->unsignedInteger('duration_seconds')->nullable();
            if (! Schema::hasColumn('course_content_progress', 'progress_percent')) $table->decimal('progress_percent', 5, 2)->default(0);
        });
    }
    public function down(): void
    {
        Schema::table('course_content_progress', function (Blueprint $table): void {
            foreach (['progress_percent', 'duration_seconds', 'last_position_seconds'] as $column) {
                if (Schema::hasColumn('course_content_progress', $column)) $table->dropColumn($column);
            }
        });
    }
};
