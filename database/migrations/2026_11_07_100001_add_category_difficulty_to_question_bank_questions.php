<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_bank_questions', function (Blueprint $table) {
            $table->string('category', 80)->nullable()->index();
            $table->string('difficulty', 12)->default('medium')->index();
        });
    }

    public function down(): void
    {
        Schema::table('question_bank_questions', function (Blueprint $table) {
            $table->dropColumn(['category', 'difficulty']);
        });
    }
};
