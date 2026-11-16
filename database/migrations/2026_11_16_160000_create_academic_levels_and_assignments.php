<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('academic_level_id')->nullable()->after('teacher_id')->constrained('academic_levels')->nullOnDelete();
            $table->index(['academic_level_id', 'status']);
        });

        Schema::table('website_pages', function (Blueprint $table): void {
            $table->foreignId('academic_level_id')->nullable()->after('created_by')->constrained('academic_levels')->nullOnDelete();
            $table->index(['academic_level_id', 'status']);
        });
    }

    public function down(): void
    {
        $hasLevelRecords = Schema::hasTable('academic_levels') && DB::table('academic_levels')->exists();
        $hasCourseAssignments = Schema::hasTable('courses')
            && Schema::hasColumn('courses', 'academic_level_id')
            && DB::table('courses')->whereNotNull('academic_level_id')->exists();
        $hasPageAssignments = Schema::hasTable('website_pages')
            && Schema::hasColumn('website_pages', 'academic_level_id')
            && DB::table('website_pages')->whereNotNull('academic_level_id')->exists();

        if ($hasLevelRecords || $hasCourseAssignments || $hasPageAssignments) {
            throw new RuntimeException('Academic levels contain records or assignments; export or explicitly clear them before rolling back this migration.');
        }

        Schema::table('website_pages', function (Blueprint $table): void {
            $table->dropIndex(['academic_level_id', 'status']);
            $table->dropConstrainedForeignId('academic_level_id');
        });
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['academic_level_id', 'status']);
            $table->dropConstrainedForeignId('academic_level_id');
        });
        Schema::dropIfExists('academic_levels');
    }
};
