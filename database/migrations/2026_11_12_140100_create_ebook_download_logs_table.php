<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebook_download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('download_permission_id')->constrained('download_permissions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('action', 16);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('downloaded_at');
            $table->index(['product_id', 'downloaded_at']);
            $table->index(['user_id', 'downloaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_download_logs');
    }
};
