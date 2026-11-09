<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title',180);
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->string('status',16)->default('published');
            $table->timestamps();
            $table->index(['course_id','status','position']);
        });
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('course_id')->constrained('course_sections')->nullOnDelete();
            $table->index(['course_id','section_id','order']);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['course_id','section_id','order']);
            $table->dropConstrainedForeignId('section_id');
        });
        Schema::dropIfExists('course_sections');
    }
};
