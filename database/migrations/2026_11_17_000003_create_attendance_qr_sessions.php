<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_qr_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->char('nonce_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['batch_id', 'attendance_date', 'expires_at']);
        });

        Schema::create('attendance_qr_checkins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_qr_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['session_id', 'student_id'], 'attendance_qr_session_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_qr_checkins');
        Schema::dropIfExists('attendance_qr_sessions');
    }
};
