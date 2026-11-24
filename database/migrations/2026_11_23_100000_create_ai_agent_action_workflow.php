<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_agent_action_proposals')) {
            Schema::create('ai_agent_action_proposals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('agent_role', 32);
                $table->string('action_key', 80);
                $table->string('status', 20)->default('pending')->index();
                $table->json('payload');
                $table->char('payload_hash', 64);
                $table->string('idempotency_key', 64)->unique();
                $table->unsignedInteger('version')->default(1);
                $table->text('review_note')->nullable();
                $table->timestamp('expires_at')->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->timestamps();
                $table->index(['requester_id', 'status', 'created_at'], 'ai_agent_proposal_owner_status_idx');
                $table->index(['agent_role', 'action_key', 'status'], 'ai_agent_proposal_action_status_idx');
            });
        }

        if (! Schema::hasTable('ai_learning_plans')) {
            Schema::create('ai_learning_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('source_proposal_id')->unique()->constrained('ai_agent_action_proposals')->restrictOnDelete();
                $table->string('title', 120);
                $table->text('goals');
                $table->json('schedule');
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->index(['student_id', 'status', 'created_at'], 'ai_learning_plan_student_status_idx');
            });
        }
    }

    public function down(): void
    {
        $hasPlans = Schema::hasTable('ai_learning_plans') && DB::table('ai_learning_plans')->exists();
        $hasProposals = Schema::hasTable('ai_agent_action_proposals') && DB::table('ai_agent_action_proposals')->exists();
        if ($hasPlans || $hasProposals) {
            throw new RuntimeException('AI agent proposals or learning plans exist. Export or explicitly retain this workflow data before rollback.');
        }

        Schema::dropIfExists('ai_learning_plans');
        Schema::dropIfExists('ai_agent_action_proposals');
    }
};
