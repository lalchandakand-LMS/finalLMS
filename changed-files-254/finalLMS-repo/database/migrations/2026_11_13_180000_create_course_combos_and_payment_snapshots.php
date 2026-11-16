<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_combos', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::create('course_combo_course', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_combo_id')->constrained('course_combos')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['course_combo_id', 'course_id']);
            $table->index(['course_combo_id', 'position']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('course_combo_id')
                ->nullable()
                ->after('course_id')
                ->constrained('course_combos')
                ->restrictOnDelete();
        });

        Schema::create('payment_combo_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('course_title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['payment_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_combo_courses');
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_combo_id');
        });
        Schema::dropIfExists('course_combo_course');
        Schema::dropIfExists('course_combos');
    }
};
