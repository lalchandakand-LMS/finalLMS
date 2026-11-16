<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_update_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preparation_id')->nullable()->constrained('system_update_preparations')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 64);
            $table->string('status', 16);
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['event', 'status', 'created_at'], 'system_update_events_event_status_created_idx');
            $table->index(['preparation_id', 'created_at'], 'system_update_events_preparation_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_update_events');
    }
};
