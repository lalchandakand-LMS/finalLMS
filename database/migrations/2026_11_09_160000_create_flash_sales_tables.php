<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('badge', 60)->default('FLASH SALE');
            $table->text('description')->nullable();
            $table->string('banner_path')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed_price']);
            $table->decimal('discount_value', 10, 2);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['draft', 'active', 'paused', 'archived'])->default('draft');
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('per_student_limit')->default(1);
            $table->unsignedInteger('priority')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
        });
        Schema::create('flash_sale_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['flash_sale_id', 'course_id']);
            $table->index('course_id');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('flash_sale_id')->nullable()->after('course_id')->constrained('flash_sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flash_sale_id');
        });
        Schema::dropIfExists('flash_sale_course');
        Schema::dropIfExists('flash_sales');
    }
};
