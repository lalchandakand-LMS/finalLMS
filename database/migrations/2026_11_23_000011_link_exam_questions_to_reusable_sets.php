<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->foreignId('question_set_question_id')->nullable()->after('question_bank_question_id')
                ->constrained('question_set_questions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('question_set_question_id');
        });
    }
};
