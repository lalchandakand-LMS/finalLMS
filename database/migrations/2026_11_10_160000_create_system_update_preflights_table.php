<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_update_preflights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preparation_id')->constrained('system_update_preparations')->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16);
            $table->json('checks');
            $table->text('summary');
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['preparation_id', 'checked_at']);
            $table->index(['status', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_update_preflights');
    }
};
