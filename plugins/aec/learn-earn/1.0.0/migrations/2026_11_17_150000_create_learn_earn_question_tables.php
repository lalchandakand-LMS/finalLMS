<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learn_earn_categories')) {
            Schema::create('learn_earn_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120)->unique();
                $table->string('slug', 150)->unique();
                $table->string('description', 1000)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['is_active', 'name']);
            });
        }

        if (! Schema::hasTable('learn_earn_questions')) {
            Schema::create('learn_earn_questions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('category_id')->nullable()->constrained('learn_earn_categories')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('subject', 120)->nullable();
                $table->string('difficulty', 20)->default('medium');
                $table->text('question');
                $table->json('options');
                $table->unsignedTinyInteger('correct_option');
                $table->unsignedSmallInteger('marks')->default(1);
                $table->unsignedSmallInteger('negative_marks')->default(0);
                $table->text('explanation')->nullable();
                $table->string('status', 20)->default('draft');
                $table->timestamps();
                $table->index(['category_id', 'status']);
                $table->index(['status', 'difficulty']);
                $table->index(['created_by', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        $hasQuestionData = Schema::hasTable('learn_earn_questions')
            && DB::table('learn_earn_questions')->exists();
        $hasCategoryData = Schema::hasTable('learn_earn_categories')
            && DB::table('learn_earn_categories')->exists();

        if ($hasQuestionData || $hasCategoryData) {
            throw new \RuntimeException('Learn & Earn questions or categories exist; preserve them during plugin migration rollback.');
        }

        Schema::dropIfExists('learn_earn_questions');
        Schema::dropIfExists('learn_earn_categories');
    }
};
