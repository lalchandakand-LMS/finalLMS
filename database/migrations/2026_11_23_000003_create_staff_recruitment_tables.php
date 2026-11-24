<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('staff_job_openings')) {
            Schema::create('staff_job_openings', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 180);
                $table->string('department', 100);
                $table->string('designation', 120);
                $table->text('description');
                $table->string('employment_type', 24);
                $table->string('location', 160)->nullable();
                $table->timestamp('closes_at')->nullable();
                $table->string('status', 20)->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'closes_at']);
            });
        }
        if (! Schema::hasTable('staff_applications')) {
            Schema::create('staff_applications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_job_opening_id')->constrained('staff_job_openings')->restrictOnDelete();
                $table->string('applicant_name', 160);
                $table->string('email', 160);
                $table->string('phone', 40);
                $table->text('cover_letter')->nullable();
                $table->string('resume_path', 255);
                $table->string('status', 24)->default('submitted');
                $table->text('review_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['staff_job_opening_id', 'status']);
                $table->index(['email', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_applications');
        Schema::dropIfExists('staff_job_openings');
    }
};
