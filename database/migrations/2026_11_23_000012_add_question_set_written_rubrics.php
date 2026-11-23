<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('question_set_questions', function (Blueprint $table): void {
            $table->json('grading_rubric')->nullable()->after('explanation');
        });
        Schema::table('question_set_attempt_questions', function (Blueprint $table): void {
            $table->json('grading_rubric')->nullable()->after('explanation');
        });
    }

    public function down(): void
    {
        if (DB::table('question_set_questions')->whereNotNull('grading_rubric')->exists()
            || DB::table('question_set_attempt_questions')->whereNotNull('grading_rubric')->exists()) {
            throw new RuntimeException('Cannot remove reusable written-question rubrics while saved rubrics exist.');
        }
        Schema::table('question_set_attempt_questions', function (Blueprint $table): void {
            $table->dropColumn('grading_rubric');
        });
        Schema::table('question_set_questions', function (Blueprint $table): void {
            $table->dropColumn('grading_rubric');
        });
    }
};
