<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_version_history', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_key', 160);
            $table->string('version', 80);
            $table->char('package_sha256', 64);
            $table->string('signature_key_id', 120);
            $table->string('package_path', 500);
            $table->string('status', 24)->default('installed');
            $table->timestamp('installed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['plugin_key', 'version']);
            $table->index(['plugin_key', 'installed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_version_history');
    }
};
