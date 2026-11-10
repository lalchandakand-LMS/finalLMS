<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_update_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preparation_id')->constrained('system_update_preparations')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code_snapshot_path', 255);
            $table->char('code_snapshot_sha256', 64);
            $table->unsignedBigInteger('code_snapshot_size');
            $table->string('database_backup_path', 255);
            $table->char('database_backup_sha256', 64);
            $table->timestamp('database_backup_created_at');
            $table->string('status', 24)->default('verified');
            $table->text('message');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['preparation_id', 'status', 'created_at']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_update_backups');
    }
};
