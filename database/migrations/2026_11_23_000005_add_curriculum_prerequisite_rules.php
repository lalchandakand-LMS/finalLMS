<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_curriculum_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('course_curriculum_items', 'unlock_after_previous')) $table->boolean('unlock_after_previous')->default(false);
            if (! Schema::hasColumn('course_curriculum_items', 'prerequisite_post_id')) $table->foreignId('prerequisite_post_id')->nullable()->constrained('course_content_posts')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('course_curriculum_items', function (Blueprint $table): void {
            if (Schema::hasColumn('course_curriculum_items', 'prerequisite_post_id')) $table->dropConstrainedForeignId('prerequisite_post_id');
            if (Schema::hasColumn('course_curriculum_items', 'unlock_after_previous')) $table->dropColumn('unlock_after_previous');
        });
    }
};
