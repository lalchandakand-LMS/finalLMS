<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_book_id')->constrained('library_books')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('waiting');
            $table->unsignedInteger('queue_position')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->index(['library_book_id', 'status', 'created_at'], 'lib_reservation_queue_idx');
            $table->index(['user_id', 'status'], 'lib_reservation_user_status_idx');
            $table->index(['status', 'expires_at'], 'lib_reservation_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_reservations');
    }
};
