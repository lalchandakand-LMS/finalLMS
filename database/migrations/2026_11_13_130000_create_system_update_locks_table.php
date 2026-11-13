<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_update_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('operation', 64)->nullable();
            $table->char('token', 64)->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acquired_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        DB::table('system_update_locks')->insert([
            'id' => 1,
            'operation' => null,
            'token' => null,
            'locked_by' => null,
            'acquired_at' => null,
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_update_locks');
    }
};
