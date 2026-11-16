<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
        if (Schema::hasTable('plugin_settings') && DB::table('plugin_settings')->exists()) {
            throw new RuntimeException('Plugin settings contain encrypted configuration; export or explicitly clear them before rolling back this migration.');
        }

        Schema::dropIfExists('plugin_settings');
    }
};
