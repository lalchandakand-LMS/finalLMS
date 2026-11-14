<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->change();
            $table->timestamp('last_viewed_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        DB::table('lesson_progress')->whereNull('completed_at')->delete();

        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->dropColumn('last_viewed_at');
            $table->timestamp('completed_at')->nullable(false)->change();
        });
    }
};
