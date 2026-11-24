<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_content_progress')) {
            Schema::table('course_content_progress', function (Blueprint $table): void {
                if (! Schema::hasColumn('course_content_progress', 'progress_percent')) {
                    $table->decimal('progress_percent', 5, 2)->default(0)->after('completed_at');
                }
                if (! Schema::hasColumn('course_content_progress', 'watched_seconds')) {
                    $table->unsignedInteger('watched_seconds')->default(0)->after('progress_percent');
                }
                if (! Schema::hasColumn('course_content_progress', 'duration_seconds')) {
                    $table->unsignedInteger('duration_seconds')->default(0)->after('watched_seconds');
                }
                if (! Schema::hasColumn('course_content_progress', 'last_position_seconds')) {
                    $table->unsignedInteger('last_position_seconds')->default(0)->after('duration_seconds');
                }
            });
        }

        if (Schema::hasTable('exams') && ! Schema::hasColumn('exams', 'result_status')) {
            Schema::table('exams', function (Blueprint $table): void {
                $table->string('result_status', 30)->default('hidden')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exams') && Schema::hasColumn('exams', 'result_status')) {
            Schema::table('exams', function (Blueprint $table): void {
                $table->dropColumn('result_status');
            });
        }

        if (Schema::hasTable('course_content_progress')) {
            $columns = ['last_position_seconds', 'duration_seconds', 'watched_seconds', 'progress_percent'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('course_content_progress', $column)) {
                    Schema::table('course_content_progress', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
