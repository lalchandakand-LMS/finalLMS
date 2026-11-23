<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_connector_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 80);
            $table->string('provider_event_id', 190);
            $table->string('event_type', 100);
            $table->string('external_account_id', 190)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['provider_key', 'provider_event_id']);
            $table->index(['provider_key', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_connector_events');
    }
};
