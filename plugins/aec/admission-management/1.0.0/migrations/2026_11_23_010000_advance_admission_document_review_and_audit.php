<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('admission_applications') && ! Schema::hasColumn('admission_applications', 'document_verification_status')) {
            Schema::table('admission_applications', function (Blueprint $table): void {
                $table->string('document_verification_status', 32)->default('pending')->after('status');
            });
        }

        if (Schema::hasTable('admission_documents')) {
            Schema::table('admission_documents', function (Blueprint $table): void {
                if (! Schema::hasColumn('admission_documents', 'document_type')) {
                    $table->string('document_type', 40)->default('other');
                }
                if (! Schema::hasColumn('admission_documents', 'review_status')) {
                    $table->string('review_status', 24)->default('pending');
                }
                if (! Schema::hasColumn('admission_documents', 'review_notes')) {
                    $table->text('review_notes')->nullable();
                }
                if (! Schema::hasColumn('admission_documents', 'reviewed_by')) {
                    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('admission_documents', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable();
                }
            });
            if (! Schema::hasIndex('admission_documents', 'admission_documents_review_idx')) {
                Schema::table('admission_documents', fn (Blueprint $table) => $table->index(['review_status', 'document_type'], 'admission_documents_review_idx'));
            }
        }

        if (! Schema::hasTable('admission_application_events')) {
            Schema::create('admission_application_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event_key', 64);
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32)->nullable();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['admission_application_id', 'created_at'], 'admission_events_app_created_idx');
                $table->index(['event_key', 'created_at'], 'admission_events_key_created_idx');
            });
        }
    }

    public function down(): void
    {
        // Admission audit history and document-review evidence are retained on rollback.
    }
};
