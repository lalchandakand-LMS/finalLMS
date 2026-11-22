<?php

namespace Tests\Feature;

use Aloms\UniversalAiAssistant\AssistantSettings;
use Tests\TestCase;

class UniversalAiAgentSpecialistPromptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantSettings.php');
    }

    public function test_student_agent_supports_language_learning_and_adaptive_study_guidance_without_claiming_unavailable_marks(): void
    {
        $prompt = AssistantSettings::rolePrompt('student');

        $this->assertStringContainsString('AI Student Agent', $prompt);
        $this->assertStringContainsString('AI Learning Agent', $prompt);
        $this->assertStringContainsString('Vocabulary', $prompt);
        $this->assertStringContainsString('grammar', $prompt);
        $this->assertStringContainsString('exam-preparation', $prompt);
        $this->assertStringContainsString('Do not claim to detect academic weaknesses from marks', $prompt);
        $this->assertStringContainsString('do not perform consequential actions', strtolower($prompt));
    }

    public function test_teacher_agent_generates_reviewable_drafts_but_never_submits_grades_or_publishes_exams(): void
    {
        $prompt = AssistantSettings::rolePrompt('teacher');

        $this->assertStringContainsString('AI Teacher Agent', $prompt);
        $this->assertStringContainsString('draft question sets', strtolower($prompt));
        $this->assertStringContainsString('marking rubrics', strtolower($prompt));
        $this->assertStringContainsString('teacher review', strtolower($prompt));
        $this->assertStringContainsString('never submit grades', strtolower($prompt));
    }

    public function test_admin_and_support_agents_are_limited_to_guidance_and_verified_context(): void
    {
        $admin = AssistantSettings::rolePrompt('admin');
        $support = AssistantSettings::rolePrompt('support_staff');

        $this->assertStringContainsString('AI Admin Agent', $admin);
        $this->assertStringContainsString('aggregate reports', strtolower($admin));
        $this->assertStringContainsString('operational summaries', strtolower($admin));
        $this->assertStringContainsString('never approve admissions', strtolower($admin));
        $this->assertStringContainsString('AI Support Agent', $support);
        $this->assertStringContainsString('24/7-style procedural guidance', $support);
        $this->assertStringContainsString('Do not claim a ticket was created', $support);
    }

    public function test_all_specialist_agents_require_human_approval_before_any_future_action_execution(): void
    {
        foreach (['admin', 'branch_manager', 'teacher', 'student', 'guardian', 'support_staff'] as $role) {
            $prompt = strtolower(AssistantSettings::rolePrompt($role));

            $this->assertStringContainsString('do not perform consequential actions', $prompt);
            $this->assertStringContainsString('explicit human approval', $prompt);
        }
    }
}
