<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_sets', function (Blueprint $table) {
            $table->id();
            $table->string('title',180);
            $table->text('description')->nullable();
            $table->string('status',16)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status','created_at']);
        });
        Schema::create('question_set_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_question_id')->constrained('question_bank_questions')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->unsignedSmallInteger('marks_override')->nullable();
            $table->timestamps();
            $table->unique(['question_set_id','question_bank_question_id'],'question_set_question_unique');
            $table->index(['question_set_id','position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_set_question');
        Schema::dropIfExists('question_sets');
    }
};
