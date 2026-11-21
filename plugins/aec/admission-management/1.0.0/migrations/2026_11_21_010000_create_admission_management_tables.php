<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('admission_applications')) {
            Schema::create('admission_applications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('application_code', 40)->unique();
                $table->string('applicant_name', 160);
                $table->string('email', 160);
                $table->string('phone', 40);
                $table->string('guardian_phone', 40)->nullable();
                $table->string('desired_course', 160);
                $table->string('preferred_batch', 80)->nullable();
                $table->string('previous_education', 160)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->text('address')->nullable();
                $table->string('application_source', 32)->default('website');
                $table->string('status', 32)->default('pending');
                $table->text('reviewer_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('interview_at')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('converted_student_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('converted_at')->nullable();
                $table->foreignId('admission_fee_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'branch_id'], 'admission_status_branch_idx');
                $table->index(['email', 'application_code'], 'admission_tracking_lookup_idx');
            });
        }
        if (! Schema::hasTable('admission_documents')) {
            Schema::create('admission_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('disk', 32)->default('local');
                $table->string('path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('size_bytes');
                $table->char('sha256', 64);
                $table->timestamps();
                $table->index(['admission_application_id', 'created_at'], 'admission_documents_app_created_idx');
            });
        }
    }

    public function down(): void
    {
        // Preserve applicant records and private evidence on plugin rollback.
    }
};
