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
            $table->string('plugin_key', 160)->index();
            $table->string('version', 80);
            $table->char('package_sha256', 64)->nullable();
            $table->string('signature_key_id', 120)->nullable();
            $table->string('action', 40);
            $table->string('status', 24)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['plugin_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_version_history');
    }
};
