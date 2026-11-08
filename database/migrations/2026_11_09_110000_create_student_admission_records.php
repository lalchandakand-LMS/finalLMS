<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status', 20)->default('active')->index();
            $table->timestamp('deletion_requested_at')->nullable();
        });

        Schema::create('student_admission_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('admission_number', 40)->unique();
            $table->string('referral_code', 24)->unique();
            $table->string('source_channel', 40)->nullable();
            $table->string('source_details', 255)->nullable();
            $table->string('school_name', 180)->nullable();
            $table->string('class_name', 80)->nullable();
            $table->string('school_roll', 80)->nullable();
            $table->foreignId('interested_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->decimal('quoted_course_price', 12, 2)->nullable();
            $table->boolean('course_updates_opt_in')->default(true);
            $table->text('current_address')->nullable();
            $table->string('current_post_office', 120)->nullable();
            $table->string('current_district', 120)->nullable();
            $table->string('current_division', 120)->nullable();
            $table->string('current_country', 120)->default('Bangladesh');
            $table->text('permanent_address')->nullable();
            $table->string('permanent_post_office', 120)->nullable();
            $table->string('permanent_district', 120)->nullable();
            $table->string('permanent_division', 120)->nullable();
            $table->string('permanent_country', 120)->default('Bangladesh');
            $table->string('living_type', 24)->default('non_residential');
            $table->string('special_quota', 40)->default('none');
            $table->text('special_quota_notes')->nullable();
            $table->string('admission_status', 24)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guardian_type', 24);
            $table->string('relationship', 60);
            $table->string('name', 180);
            $table->string('mobile_number', 20);
            $table->string('email')->nullable();
            $table->string('occupation', 120)->nullable();
            $table->string('nid_number', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('contact_consent')->default(true);
            $table->timestamps();
            $table->index(['student_id', 'is_primary']);
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('student_guardians')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->string('encrypted_path', 500)->unique();
            $table->char('content_hash', 64);
            $table->timestamps();
            $table->index(['student_id', 'document_type']);
        });

        Schema::create('student_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_student_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('referral_code', 24);
            $table->string('status', 20)->default('registered');
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
            $table->index(['course_id', 'starts_at']);
        });

        Schema::create('student_course_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('student_course_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->string('check_in_method', 24)->default('student');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_course_attendances');
        Schema::dropIfExists('student_course_sessions');
        Schema::dropIfExists('student_referrals');
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('student_admission_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_status', 'deletion_requested_at']);
        });
    }
};
