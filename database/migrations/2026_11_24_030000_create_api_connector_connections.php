<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_connector_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 80);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_account_id', 190)->nullable();
            $table->longText('credentials');
            $table->json('granted_scopes')->nullable();
            $table->json('capabilities')->nullable();
            $table->string('status', 32)->default('needs_setup');
            $table->timestamp('webhook_verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
            $table->index(['provider_key', 'status']);
            $table->unique(['provider_key', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_connector_connections');
    }
};
