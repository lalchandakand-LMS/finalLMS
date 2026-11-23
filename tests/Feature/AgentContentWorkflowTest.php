<?php

namespace Tests\Feature;

use Aloms\UniversalAiAssistant\AgentContentWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgentContentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantAudit.php');
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AgentContentWorkflow.php');
        if (! Schema::hasTable('ai_assistant_audit_events')) {
            $auditMigration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_16_150000_create_ai_assistant_audit_events.php');
            $auditMigration->up();
        }
        if (! Schema::hasTable('ai_teacher_drafts')) {
            $migration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_23_110000_create_ai_agent_content_tables.php');
            $migration->up();
        }
    }

    public function test_teacher_can_save_draft_and_submit_it_for_admin_review_with_notification(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(AgentContentWorkflow::class);

        $draft = $workflow->saveTeacherDraft($teacher, [
            'draft_type' => 'question_set',
            'title' => 'Present tense quiz',
            'content' => 'Create ten age-appropriate questions.',
            'submit_for_review' => '1',
        ]);

        $this->assertSame('pending_review', $draft->status);
        $this->assertDatabaseHas('ai_assistant_audit_events', ['event' => 'teacher_draft_submitted', 'user_id' => $teacher->id]);
        $this->assertDatabaseHas('ai_agent_notifications', [
            'user_id' => $admin->id,
            'event' => 'teacher_draft_submitted',
        ]);
        $this->assertDatabaseHas('ai_teacher_drafts', ['id' => $draft->id, 'author_id' => $teacher->id]);
    }

    public function test_admin_can_approve_teacher_draft_and_author_receives_private_notification(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(AgentContentWorkflow::class);
        $draft = $workflow->saveTeacherDraft($teacher, [
            'draft_type' => 'lesson_plan',
            'title' => 'Past simple lesson',
            'content' => 'Warm-up, explanation, controlled practice, speaking task.',
            'submit_for_review' => true,
        ]);

        $reviewed = $workflow->reviewTeacherDraft($admin, (int) $draft->id, 1, true, 'Looks good.');

        $this->assertSame('approved', $reviewed->status);
        $this->assertSame($admin->id, $reviewed->reviewer_id);
        $this->assertDatabaseHas('ai_agent_notifications', [
            'user_id' => $teacher->id,
            'event' => 'teacher_draft_approved',
        ]);
        $this->assertSame(0, DB::table('ai_agent_notifications')->where('user_id', $admin->id)->where('event', 'teacher_draft_approved')->count());
    }

    public function test_draft_author_cannot_approve_their_own_content(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $draft = app(AgentContentWorkflow::class)->saveTeacherDraft($teacher, [
            'draft_type' => 'rubric',
            'title' => 'Speaking rubric',
            'content' => 'Fluency, vocabulary, grammar and pronunciation.',
            'submit_for_review' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        app(AgentContentWorkflow::class)->reviewTeacherDraft($teacher, (int) $draft->id, 1, true);
    }

    public function test_support_retrieval_returns_only_published_entries_and_includes_citations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(AgentContentWorkflow::class);
        $published = $workflow->saveKnowledge($admin, [
            'category' => 'Course access',
            'question' => 'How do I access recorded lessons?',
            'answer' => 'Open My Courses and select the lesson.',
            'source_label' => 'Student guide',
            'source_url' => 'https://example.com/student-guide',
        ]);
        DB::table('ai_support_knowledge')->insert([
            'created_by' => $admin->id,
            'category' => 'Private',
            'question' => 'Unpublished internal note',
            'answer' => 'Do not show this.',
            'status' => 'draft',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $workflow->searchKnowledge('recorded lessons', 5);

        $this->assertCount(1, $results);
        $this->assertSame($published->id, $results[0]->id);
        $this->assertSame('Student guide', $results[0]->source_label);
        $this->assertDatabaseHas('ai_assistant_audit_events', ['event' => 'support_knowledge_published', 'user_id' => $admin->id]);
    }
}
