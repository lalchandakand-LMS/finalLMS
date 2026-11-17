<?php

namespace Tests\Feature;

use Aloms\UniversalAiAssistant\AssistantSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UniversalAiAssistantRoleGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantSettings.php');
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantTokenBudget.php');
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantController.php');

        if (! Schema::hasTable('ai_assistant_settings')) {
            $migration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_15_180000_create_universal_ai_assistant_tables.php');
            $migration->up();
        }
        if (Schema::hasTable('ai_assistant_settings') && ! Schema::hasColumn('ai_assistant_settings', 'retention_days')) {
            $migration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_15_190000_add_ai_assistant_conversation_retention.php');
            $migration->up();
        }
        if (! Schema::hasTable('ai_assistant_daily_budgets')) {
            $migration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_17_090000_create_ai_assistant_token_budget_tables.php');
            $migration->up();
        }

        if (Schema::hasTable('ai_assistant_settings') && ! Schema::hasColumn('ai_assistant_settings', 'role_data_access')) {
            $migration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_17_110000_add_ai_assistant_role_data_access.php');
            $migration->up();
        }

        Route::post('/__test-ai-role-gate/messages', [\Aloms\UniversalAiAssistant\AssistantController::class, 'send']);
    }

    public function test_every_supported_role_is_denied_by_the_server_when_its_switch_is_off(): void
    {
        AssistantSettings::save([
            'model' => 'gpt-4o-mini',
            'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.4,
            'max_tokens' => 800,
            'global_enabled' => true,
            'role_enabled' => array_fill_keys(AssistantSettings::ROLES, false),
            'daily_request_limit' => 20,
            'daily_token_budget' => 50000,
            'retention_days' => 365,
        ], 'test-secret-provider-key', false);

        foreach ([
            'admin',
            'branch_manager',
            'teacher',
            'student',
            'guardian',
            'accountant',
        ] as $userRole) {
            $user = User::factory()->create(['role' => $userRole]);

            $this->actingAs($user)->postJson('/__test-ai-role-gate/messages', [
                'message' => 'Please help me practise English.',
            ])->assertNotFound();
        }

        $this->assertSame(0, DB::table('ai_assistant_conversations')->count());
        $this->assertSame(0, DB::table('ai_assistant_messages')->count());
    }
}
