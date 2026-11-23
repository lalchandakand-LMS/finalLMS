<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_connections')) return;

        Schema::create('api_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 64)->index();
            $table->string('display_name', 120);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('external_account_id', 190)->nullable();
            $table->longText('credentials_encrypted')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->json('capabilities')->nullable();
            $table->string('status', 32)->default('needs_setup')->index();
            $table->boolean('webhook_verified')->default(false);
            $table->timestamp('last_credential_check_at')->nullable();
            $table->timestamp('last_webhook_event_at')->nullable();
            $table->timestamp('last_successful_operation_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['provider_key', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_connections');
    }
};