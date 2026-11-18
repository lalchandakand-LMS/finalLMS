<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('event_key', 190)->unique();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_payment_id')->nullable()->constrained('invoice_payments')->nullOnDelete();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->string('event_type', 80);
            $table->string('verification_status', 24)->default('received');
            $table->string('processing_status', 24)->default('received');
            $table->char('payload_hash', 64)->nullable();
            $table->json('verified_payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['provider', 'verification_status', 'received_at']);
        });

        Schema::create('payment_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_payment_id')->nullable()->constrained('invoice_payments')->nullOnDelete();
            $table->foreignId('invoice_refund_id')->nullable()->constrained('invoice_refunds')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('event', 80);
            $table->string('source', 40);
            $table->string('correlation_id', 100)->nullable()->unique();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['payment_id', 'created_at']);
            $table->index(['invoice_refund_id', 'created_at']);
        });

        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_payment_id')->nullable()->constrained('invoice_payments')->nullOnDelete();
            $table->string('provider', 40);
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->string('result', 24);
            $table->string('local_status', 24)->nullable();
            $table->string('gateway_status', 40)->nullable();
            $table->decimal('local_amount', 12, 2)->nullable();
            $table->decimal('gateway_amount', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('details')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['result', 'checked_at']);
        });
    }

    public function down(): void
    {
        foreach (['payment_reconciliations', 'payment_audit_events', 'payment_gateway_events'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException("Refusing to drop non-empty {$table}; payment security history must be preserved.");
            }
        }
        Schema::dropIfExists('payment_reconciliations');
        Schema::dropIfExists('payment_audit_events');
        Schema::dropIfExists('payment_gateway_events');
    }
};
