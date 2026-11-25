<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_inventory_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_book_id')->constrained('library_books')->restrictOnDelete();
            $table->foreignId('library_loan_id')->unique()->constrained('library_loans')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition', 16);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->text('notes');
            $table->timestamps();
            $table->index(['branch_id', 'condition', 'created_at'], 'lib_inventory_adjust_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_inventory_adjustments');
    }
};
