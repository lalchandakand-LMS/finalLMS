<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_key', 160);
            $table->string('setting_key', 120);
            $table->longText('encrypted_value');
            $table->timestamp('updated_at')->nullable();

            $table->unique(['plugin_key', 'setting_key']);
            $table->index('plugin_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_settings');
    }
};
