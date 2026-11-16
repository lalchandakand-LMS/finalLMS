<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_update_preparations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('version', 32);
            $table->char('package_sha256', 64);
            $table->char('manifest_sha256', 64);
            $table->string('package_path', 255);
            $table->unsignedBigInteger('file_size');
            $table->string('status', 24)->default('verified');
            $table->text('message');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['version', 'package_sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_update_preparations');
    }
};
