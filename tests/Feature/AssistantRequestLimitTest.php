<?php

namespace Tests\\Feature;

use Aloms\\UniversalAiAssistant\\AssistantRequestLimit;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\Schema;
use Tests\\TestCase;

class AssistantRequestLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantRequestLimit.php');
        $migration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_17_100000_create_ai_assistant_user_daily_requests.php');
        $migration->up();
    }

    public function test_daily_request_limit_is_enforced_and_scoped_per_user(): void
    {
        $firstUser = \\App\\Models\\User::factory()->create();
        $secondUser = \\App\\Models\\User::factory()->create();

        $this->assertTrue(AssistantRequestLimit::reserve($firstUser->id, 2));
        $this->assertTrue(AssistantRequestLimit::reserve($firstUser->id, 2));
        $this->assertFalse(AssistantRequestLimit::reserve($firstUser->id, 2));
        $this->assertTrue(AssistantRequestLimit::reserve($secondUser->id, 2));

        $this->assertDatabaseHas('ai_assistant_user_daily_requests', [
            'user_id' => $firstUser->id,
            'request_date' => today()->toDateString(),
            'request_count' => 2,
        ]);
    }

    public function test_invalid_limits_fail_closed(): void
    {
        $user = \\App\\Models\\User::factory()->create();

        $this->assertFalse(AssistantRequestLimit::reserve($user->id, 0));
        $this->assertFalse(AssistantRequestLimit::reserve(0, 10));
    }
}
