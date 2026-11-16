<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_payment_id')->constrained('invoice_payments')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 40);
            $table->string('reference', 120)->nullable();
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
            $table->index(['invoice_payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_refunds');
    }
};