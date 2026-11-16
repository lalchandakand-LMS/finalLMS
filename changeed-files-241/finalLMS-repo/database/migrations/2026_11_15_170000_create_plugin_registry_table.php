<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_registry', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_key', 160)->unique();
            $table->string('name', 120);
            $table->string('version', 80);
            $table->string('type', 32);
            $table->string('status', 24)->default('discovered')->index();
            $table->json('manifest');
            $table->char('package_sha256', 64)->nullable();
            $table->string('signature_key_id', 120)->nullable();
            $table->timestamp('signature_verified_at')->nullable();
            $table->string('package_path', 500)->nullable();
            $table->string('health_status', 24)->default('unknown');
            $table->text('health_message')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['health_status', 'last_health_check_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_registry');
    }
};
