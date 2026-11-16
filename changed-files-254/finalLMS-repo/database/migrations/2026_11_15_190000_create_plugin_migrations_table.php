<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_migrations', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_key', 160);
            $table->string('plugin_version', 80);
            $table->string('migration', 190);
            $table->string('status', 24)->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['plugin_key', 'plugin_version', 'migration'], 'plugin_migrations_identity_unique');
            $table->index(['plugin_key', 'plugin_version', 'status'], 'plugin_migrations_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_migrations');
    }
};
