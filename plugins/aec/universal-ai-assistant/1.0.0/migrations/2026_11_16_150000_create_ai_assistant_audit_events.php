<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_assistant_audit_events')) {
            Schema::create('ai_assistant_audit_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->unsignedBigInteger('reservation_id')->nullable()->index();
                $table->string('assistant_role', 32);
                $table->string('provider', 40);
                $table->string('model', 120);
                $table->string('event', 48);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['assistant_role', 'event', 'created_at'], 'ai_assistant_audit_role_event_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_assistant_audit_events') && DB::table('ai_assistant_audit_events')->exists()) {
            throw new \RuntimeException('Export or explicitly erase AI audit events before rolling back this migration.');
        }
        Schema::dropIfExists('ai_assistant_audit_events');
    }
};
