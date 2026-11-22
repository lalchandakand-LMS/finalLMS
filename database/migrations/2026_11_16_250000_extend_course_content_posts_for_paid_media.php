<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_content_posts', function (Blueprint $table): void {
            $table->string('thumbnail_path')->nullable()->after('thumbnail_media_id');
            $table->string('content_file_path')->nullable();
            $table->string('content_file_name')->nullable();
            $table->string('content_file_mime', 120)->nullable();
            $table->unsignedBigInteger('content_file_size')->nullable();
            $table->string('access_type', 24)->default('course_enrollment')->index();
            $table->decimal('regular_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->foreignId('academic_level_id')->nullable()->constrained('academic_levels')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->unique()->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_content_posts', function (Blueprint $table): void {
            // The FK relies on the unique index; remove the constraint before its index.
            $table->dropForeign(['product_id']);
            $table->dropUnique(['product_id']);
            $table->dropColumn('product_id');
            $table->dropConstrainedForeignId('academic_level_id');
            $table->dropColumn([
                'thumbnail_path', 'content_file_path', 'content_file_name',
                'content_file_mime', 'content_file_size', 'access_type',
                'regular_price', 'sale_price',
            ]);
        });
    }
};
