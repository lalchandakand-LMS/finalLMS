<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']);
        });
    }
};
