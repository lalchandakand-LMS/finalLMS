<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_class_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_class_id')->constrained('live_classes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('marked_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['live_class_id', 'student_id'], 'live_class_attendance_class_student_unique');
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('live_class_attendances')->exists()) {
            throw new RuntimeException('Class attendance records are financial evidence; retain them once recorded.');
        }

        Schema::dropIfExists('live_class_attendances');
    }
};
