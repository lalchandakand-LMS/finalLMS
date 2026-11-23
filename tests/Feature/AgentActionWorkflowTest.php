<?php

namespace Tests\Feature;

use Aloms\UniversalAiAssistant\AgentActionWorkflow;
use Aloms\UniversalAiAssistant\AssistantAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class AgentActionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantAudit.php');
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AgentActionWorkflow.php');

        if (! Schema::hasTable('ai_assistant_audit_events')) {
            $migration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_16_150000_create_ai_assistant_audit_events.php');
            $migration->up();
        }
        if (! Schema::hasTable('ai_agent_action_proposals')) {
            $migration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_23_100000_create_ai_agent_action_workflow.php');
            $migration->up();
        }
    }

    public function test_student_proposal_is_idempotent_and_waits_for_human_approval(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $workflow = app(AgentActionWorkflow::class);
        $payload = [
            'title' => 'English speaking practice',
            'goals' => 'Build confidence in everyday conversation.',
            'schedule' => [['day' => 'Saturday', 'focus' => 'Vocabulary and speaking', 'minutes' => 30]],
        ];

        $first = $workflow->proposeStudyPlan($student, $payload, 'study-plan-request-0001');
        $retry = $workflow->proposeStudyPlan($student, $payload, 'study-plan-request-0001');

        $this->assertSame($first->id, $retry->id);
        $this->assertSame('pending', $first->status);
        $this->assertSame(0, DB::table('ai_learning_plans')->count());
        $this->assertDatabaseHas('ai_assistant_audit_events', [
            'event' => 'agent_action_proposed',
            'user_id' => $student->id,
        ]);
    }

    public function test_requester_cannot_approve_own_proposal(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $workflow = app(AgentActionWorkflow::class);
        $proposal = $workflow->proposeStudyPlan($student, [
            'title' => 'Personal revision',
            'goals' => 'Revise grammar every week.',
            'schedule' => [['day' => 'Sunday', 'focus' => 'Grammar', 'minutes' => 25]],
        ], 'study-plan-request-0002');

        $student->forceFill(['role' => 'admin'])->save();
        $student->refresh();
        try {
            $workflow->approve($student, (int) $proposal->id, 1);
            $this->fail('A requester must not approve their own proposal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('requester cannot approve their own', strtolower($exception->getMessage()));
        }

        $this->assertSame('pending', DB::table('ai_agent_action_proposals')->where('id', $proposal->id)->value('status'));
        $this->assertSame(0, DB::table('ai_learning_plans')->count());
    }

    public function test_admin_approval_executes_once_and_records_the_approver(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(AgentActionWorkflow::class);
        $proposal = $workflow->proposeStudyPlan($student, [
            'title' => 'Exam preparation plan',
            'goals' => 'Improve vocabulary and grammar for the final exam.',
            'schedule' => [['day' => 'Monday', 'focus' => 'Vocabulary', 'minutes' => 40]],
        ], 'study-plan-request-0003');

        $approved = $workflow->approve($admin, (int) $proposal->id, 1);

        $this->assertSame('executed', $approved->status);
        $this->assertDatabaseHas('ai_learning_plans', [
            'student_id' => $student->id,
            'approved_by' => $admin->id,
            'source_proposal_id' => $proposal->id,
            'title' => 'Exam preparation plan',
        ]);
        $this->assertDatabaseHas('ai_agent_action_proposals', [
            'id' => $proposal->id,
            'status' => 'executed',
            'approver_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('ai_assistant_audit_events', [
            'event' => 'agent_action_executed',
            'user_id' => $admin->id,
        ]);

        try {
            $workflow->approve($admin, (int) $proposal->id, 1);
            $this->fail('An executed proposal must not execute a second time.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no longer pending', $exception->getMessage());
        }

        $this->assertSame(1, DB::table('ai_learning_plans')->where('source_proposal_id', $proposal->id)->count());
    }

    public function test_tampered_proposal_payload_is_blocked_before_execution(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(AgentActionWorkflow::class);
        $proposal = $workflow->proposeStudyPlan($student, [
            'title' => 'Safe plan',
            'goals' => 'Study safely.',
            'schedule' => [['day' => 'Friday', 'focus' => 'Reading', 'minutes' => 20]],
        ], 'study-plan-request-0004');

        DB::table('ai_agent_action_proposals')->where('id', $proposal->id)->update([
            'payload' => json_encode(['title' => 'Tampered plan', 'goals' => 'Altered', 'schedule' => []]),
        ]);

        try {
            $workflow->approve($admin, (int) $proposal->id, 1);
            $this->fail('A modified proposal must fail its integrity check.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('integrity check failed', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('ai_learning_plans')->count());
        $this->assertSame('pending', DB::table('ai_agent_action_proposals')->where('id', $proposal->id)->value('status'));
    }

    public function test_expired_proposal_is_marked_expired_and_never_executes(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $workflow = app(AgentActionWorkflow::class);
        $proposal = $workflow->proposeStudyPlan($student, [
            'title' => 'Expired plan',
            'goals' => 'No longer current.',
            'schedule' => [['day' => 'Tuesday', 'focus' => 'Reading', 'minutes' => 20]],
        ], 'study-plan-request-0005');
        DB::table('ai_agent_action_proposals')->where('id', $proposal->id)->update(['expires_at' => now()->subMinute()]);

        try {
            $workflow->approve($admin, (int) $proposal->id, 1);
            $this->fail('Expired proposals must not execute.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('expired', strtolower($exception->getMessage()));
        }

        $this->assertSame('expired', DB::table('ai_agent_action_proposals')->where('id', $proposal->id)->value('status'));
        $this->assertSame(0, DB::table('ai_learning_plans')->count());
        $this->assertDatabaseHas('ai_assistant_audit_events', ['event' => 'agent_action_expired']);
    }

    public function test_teacher_cannot_approve_a_student_outside_the_teachers_course_scope(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $workflow = app(AgentActionWorkflow::class);
        $proposal = $workflow->proposeStudyPlan($student, [
            'title' => 'Out of scope',
            'goals' => 'Study English.',
            'schedule' => [['day' => 'Wednesday', 'focus' => 'Speaking', 'minutes' => 20]],
        ], 'study-plan-request-0006');

        try {
            $workflow->approve($teacher, (int) $proposal->id, 1);
            $this->fail('A teacher without an assigned active learner course must not approve.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Teachers may approve study plans only', $exception->getMessage());
        }

        $this->assertSame('pending', DB::table('ai_agent_action_proposals')->where('id', $proposal->id)->value('status'));
        $this->assertSame(0, DB::table('ai_learning_plans')->count());
    }

}
