<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;\nuse Illuminate\\Support\\Facades\\DB;\nuse RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_teacher_drafts')) {
            Schema::create('ai_teacher_drafts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('draft_type', 32);
                $table->string('title', 180);
                $table->longText('content');
                $table->string('status', 20)->default('draft')->index();
                $table->text('review_note')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['author_id', 'status', 'created_at'], 'ai_teacher_draft_author_status_idx');
            });
        }

        if (! Schema::hasTable('ai_support_knowledge')) {
            Schema::create('ai_support_knowledge', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('category', 60)->index();
                $table->string('question', 240);
                $table->longText('answer');
                $table->string('source_label', 180)->nullable();
                $table->string('source_url', 500)->nullable();
                $table->string('status', 20)->default('draft')->index();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->index(['status', 'category'], 'ai_support_knowledge_status_category_idx');
            });
        }

        if (! Schema::hasTable('ai_agent_notifications')) {
            Schema::create('ai_agent_notifications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('event', 80);
                $table->string('title', 180);
                $table->text('body');
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'read_at', 'created_at'], 'ai_agent_notifications_inbox_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['ai_teacher_drafts', 'ai_support_knowledge', 'ai_agent_notifications'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('AI agent content or notification data exists; export or retain it before rollback.');
            }
        }
        Schema::dropIfExists('ai_agent_notifications');
        Schema::dropIfExists('ai_support_knowledge');
        Schema::dropIfExists('ai_teacher_drafts');
    }
};
