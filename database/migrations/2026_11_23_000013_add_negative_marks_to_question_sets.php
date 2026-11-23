<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('question_set_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('negative_marks')->default(0)->after('marks');
        });
        Schema::table('question_set_attempt_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('negative_marks')->default(0)->after('marks');
        });
    }

    public function down(): void
    {
        if (DB::table('question_set_questions')->where('negative_marks', '>', 0)->exists()
            || DB::table('question_set_attempt_questions')->where('negative_marks', '>', 0)->exists()) {
            throw new RuntimeException('Cannot remove reusable question-set negative marks while configured penalties exist.');
        }
        Schema::table('question_set_attempt_questions', function (Blueprint $table): void {
            $table->dropColumn('negative_marks');
        });
        Schema::table('question_set_questions', function (Blueprint $table): void {
            $table->dropColumn('negative_marks');
        });
    }
};
