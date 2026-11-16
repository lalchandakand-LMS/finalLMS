<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('category', 80);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 40)->default('cash');
            $table->string('reference', 120)->nullable();
            $table->date('expense_date');
            $table->timestamps();
            $table->index(['branch_id', 'expense_date']);
            $table->index(['category', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
