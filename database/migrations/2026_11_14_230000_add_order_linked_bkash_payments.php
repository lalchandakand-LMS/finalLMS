<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->unsignedBigInteger('course_id')->nullable()->change();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->after('course_id')->constrained('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('payments')->whereNull('course_id')->exists()) {
            throw new RuntimeException('Cannot roll back order-linked payments while eBook gateway payment records still exist.');
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropForeign(['course_id']);
            $table->unsignedBigInteger('course_id')->nullable(false)->change();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }
};
