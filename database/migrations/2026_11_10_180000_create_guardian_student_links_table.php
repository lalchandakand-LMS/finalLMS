<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_student_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship', 40)->default('guardian');
            $table->string('status', 16)->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verification_method', 32)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['guardian_user_id', 'student_user_id']);
            $table->index(['student_user_id', 'status', 'requested_at']);
            $table->index(['guardian_user_id', 'status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student_links');
    }
};
