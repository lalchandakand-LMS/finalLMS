<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('student_number', 40)->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 30)->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('nationality', 80)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('emergency_contact_name', 160)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->string('previous_school', 180)->nullable();
            $table->date('admission_date')->nullable();
            $table->string('lifecycle_status', 24)->default('active');
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suspension_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['lifecycle_status', 'admission_date']);
        });

        Schema::create('student_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_lifecycle_events');
        Schema::dropIfExists('student_profiles');
    }
};
