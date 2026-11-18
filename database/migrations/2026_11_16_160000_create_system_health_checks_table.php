<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20)->index();
            $table->json('results');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('checked_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_checks');
    }
};
