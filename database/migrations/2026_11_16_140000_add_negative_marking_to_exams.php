<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_bank_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('negative_marks')->default(0)->after('marks');
        });

        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('negative_marks')->default(0)->after('marks');
        });

        Schema::table('exam_answers', function (Blueprint $table): void {
            $table->integer('marks_awarded')->default(0)->change();
        });
    }

    public function down(): void
    {
        // Negative scores cannot be represented by the legacy unsigned column.
        // Refuse rollback rather than failing mid-DDL or coercing historical results.
        if (DB::table('exam_answers')->where('marks_awarded', '<', 0)->exists()) {
            throw new RuntimeException(
                'Cannot roll back negative marking while exam answers contain negative awarded marks. Preserve or export those results before retrying.'
            );
        }

        Schema::table('exam_answers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('marks_awarded')->default(0)->change();
        });

        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->dropColumn('negative_marks');
        });

        Schema::table('question_bank_questions', function (Blueprint $table): void {
            $table->dropColumn('negative_marks');
        });
    }
};
