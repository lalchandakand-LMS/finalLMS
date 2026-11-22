<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('question_set_id')->nullable()->after('teacher_id')
                ->constrained('question_sets')->nullOnDelete();
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->json('question_answers')->nullable()->after('answer');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('question_answers');
        });
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('question_set_id');
        });
    }
};
