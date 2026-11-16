<?php

namespace Tests\Feature;

use Aloms\UniversalAiAssistant\AssistantSettings;
use Aloms\UniversalAiAssistant\AssistantTokenBudget;
use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\AiDeveloperProviderEndpoint;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UniversalAiAssistantPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantSettings.php');
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantTokenBudget.php');
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantController.php');
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AdminSettingsController.php');
        if (! Schema::hasTable('ai_assistant_settings')) {
            $migration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_15_180000_create_universal_ai_assistant_tables.php');
            $migration->up();
        }
        if (Schema::hasTable('ai_assistant_settings') && ! Schema::hasColumn('ai_assistant_settings', 'retention_days')) {
            $retentionMigration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_15_190000_add_ai_assistant_conversation_retention.php');
            $retentionMigration->up();
        }
        if (! Schema::hasTable('ai_assistant_token_reservations')) {
            $budgetMigration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_16_120000_create_ai_assistant_token_reservations.php');
            $budgetMigration->up();
        }
    }

    public function test_assistant_settings_are_disabled_by_default_and_private_data_access_is_denied(): void
    {
        $settings = AssistantSettings::record();
        $this->assertFalse($settings['global_enabled']);
        $this->assertSame('general_only', $settings['data_access_mode']);
        $this->assertSame(365, $settings['retention_days']);
        foreach (AssistantSettings::ROLES as $role) $this->assertFalse($settings['role_enabled'][$role]);
        $this->assertNull(AssistantSettings::apiKey());
    }

    public function test_completion_limit_reserves_prompt_tokens_and_never_exceeds_remaining_daily_budget(): void
    {
        $messages = [
            ['role' => 'system', 'content' => AssistantSettings::rolePrompt('student')],
            ['role' => 'user', 'content' => 'Hello'],
        ];

        $remaining = AssistantTokenBudget::remaining(5000, 1000);
        $estimatedPrompt = AssistantTokenBudget::estimatePromptTokens($messages);
        $limit = AssistantTokenBudget::completionLimit(8192, $remaining, $estimatedPrompt);

        $this->assertGreaterThan(0, $estimatedPrompt);
        $this->assertGreaterThan(0, $limit);
        $this->assertLessThanOrEqual($remaining - $estimatedPrompt, $limit);
        $this->assertLessThanOrEqual(8192, $limit);
        $this->assertSame(0, AssistantTokenBudget::completionLimit(1200, 100, 100));
        $this->assertSame(0, AssistantTokenBudget::remaining(1000, 1200));
    }

    public function test_daily_token_budget_reservations_are_atomic_and_released_or_settled(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $conversationId = \DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $user->id,
            'assistant_role' => 'student',
            'title' => 'Budget reservation test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first = AssistantTokenBudget::reserve(2000, 500, 1000, 20, $user->id, $conversationId);
        $this->assertNotNull($first);
        $this->assertSame(1000, $first['completion_limit']);

        // Pending reservations consume budget, preventing concurrent requests
        // from both receiving the same completion allowance.
        $second = AssistantTokenBudget::reserve(2000, 500, 1000, 20, $user->id, $conversationId);
        $this->assertNull($second);

        AssistantTokenBudget::release($first['id']);
        $retry = AssistantTokenBudget::reserve(2000, 500, 1000, 20, $user->id, $conversationId);
        $this->assertNotNull($retry);

        AssistantTokenBudget::settle($retry['id'], 900, function () use ($conversationId): void {
            \DB::table('ai_assistant_messages')->insert([
                'conversation_id' => $conversationId,
                'message_role' => 'assistant',
                'content' => 'A response',
                'token_count' => 900,
                'model' => 'test-model',
                'created_at' => now(),
            ]);
        });

        $this->assertDatabaseHas('ai_assistant_token_reservations', [
            'id' => $retry['id'],
            'status' => 'completed',
            'actual_tokens' => 900,
        ]);
        $this->assertDatabaseHas('ai_assistant_messages', [
            'conversation_id' => $conversationId,
            'message_role' => 'assistant',
            'token_count' => 900,
        ]);
    }

    public function test_per_user_daily_request_limit_is_checked_inside_the_budget_lock(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $conversationId = \DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $user->id,
            'assistant_role' => 'student',
            'title' => 'Request quota test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $first = AssistantTokenBudget::reserve(5000, 100, 1000, 1, $user->id, $conversationId);
        $this->assertNotNull($first);
        AssistantTokenBudget::release($first['id']);

        \DB::table('ai_assistant_messages')->insert([
            'conversation_id' => $conversationId,
            'message_role' => 'user',
            'content' => 'First request',
            'token_count' => null,
            'model' => null,
            'created_at' => now(),
        ]);
        $second = AssistantTokenBudget::reserve(5000, 100, 1000, 1, $user->id, $conversationId);
        $this->assertNull($second);
    }

    public function test_provider_key_is_encrypted_and_never_in_public_settings(): void
    {
        AssistantSettings::save([
            'model' => 'gpt-4o-mini', 'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.4, 'max_tokens' => 800, 'global_enabled' => false,
            'role_enabled' => array_fill_keys(AssistantSettings::ROLES, false),
            'daily_request_limit' => 20, 'daily_token_budget' => 50000,
        ], 'test-secret-provider-key', false);

        $stored = (string) \DB::table('ai_assistant_settings')->where('id', 1)->value('api_key_encrypted');
        $this->assertNotSame('test-secret-provider-key', $stored);
        $this->assertSame('test-secret-provider-key', Crypt::decryptString($stored));
        $this->assertSame('test-secret-provider-key', AssistantSettings::apiKey());
        $this->assertArrayNotHasKey('api_key_encrypted', AssistantSettings::publicSettings());
        $this->assertArrayNotHasKey('api_key', AssistantSettings::publicSettings());
    }

    public function test_provider_model_discovery_returns_model_ids_without_exposing_api_credentials(): void
    {
        AssistantSettings::save([
            'model' => 'gpt-4o-mini', 'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.4, 'max_tokens' => 800, 'global_enabled' => false,
            'role_enabled' => array_fill_keys(AssistantSettings::ROLES, false),
            'daily_request_limit' => 20, 'daily_token_budget' => 50000, 'retention_days' => 365,
        ], 'test-secret-provider-key', false);

        app()->instance(AiDeveloperProviderEndpoint::class, new class extends AiDeveloperProviderEndpoint {
            public function curlOptionsFor(string $url): array { return []; }
        });
        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'data' => [['id' => 'gpt-4o-mini'], ['id' => 'gpt-4.1'], ['id' => 'text-embedding-3-small']],
            ], 200),
        ]);

        $response = (new \Aloms\UniversalAiAssistant\AdminSettingsController())->models();
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['gpt-4.1', 'gpt-4o-mini', 'text-embedding-3-small'], $payload['models']);
        $this->assertStringNotContainsString('test-secret-provider-key', json_encode($payload));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/models');
    }

    public function test_configured_retention_prunes_expired_conversations_and_cascades_messages(): void
    {
        AssistantSettings::save([
            'model' => 'gpt-4o-mini', 'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.4, 'max_tokens' => 800, 'global_enabled' => false,
            'role_enabled' => array_fill_keys(AssistantSettings::ROLES, false),
            'daily_request_limit' => 20, 'daily_token_budget' => 50000, 'retention_days' => 30,
        ], null, false);
        $user = User::factory()->create(['role' => 'student']);
        $expiredId = \DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $user->id, 'assistant_role' => 'student', 'title' => 'Expired',
            'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40),
        ]);
        \DB::table('ai_assistant_messages')->insert([
            'conversation_id' => $expiredId, 'message_role' => 'user', 'content' => 'Old message',
            'token_count' => null, 'model' => null, 'created_at' => now()->subDays(40),
        ]);
        $activeId = \DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $user->id, 'assistant_role' => 'student', 'title' => 'Recent',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        AssistantSettings::pruneExpiredConversations();

        $this->assertDatabaseMissing('ai_assistant_conversations', ['id' => $expiredId]);
        $this->assertDatabaseMissing('ai_assistant_messages', ['conversation_id' => $expiredId]);
        $this->assertDatabaseHas('ai_assistant_conversations', ['id' => $activeId]);
    }

    public function test_sensitive_provider_tokens_are_redacted_before_saving_conversation_titles_and_sending_messages(): void
    {
        AssistantSettings::save([
            'model' => 'gpt-4o-mini', 'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.4, 'max_tokens' => 800, 'global_enabled' => true,
            'role_enabled' => array_replace(array_fill_keys(AssistantSettings::ROLES, false), ['student' => true]),
            'daily_request_limit' => 20, 'daily_token_budget' => 50000, 'retention_days' => 365,
        ], 'test-secret-provider-key', false);

        app()->instance(AiDeveloperProviderEndpoint::class, new class extends AiDeveloperProviderEndpoint {
            public function curlOptionsFor(string $url): array { return []; }
        });
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Let us practise English.']]],
                'usage' => ['total_tokens' => 12],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => 'student']);
        $secret = 'sk-ABCDEFGHIJKLMNOPQRSTUVWXYZ123456';
        Route::post('/__test-ai-assistant/messages', [\Aloms\UniversalAiAssistant\AssistantController::class, 'send']);

        $response = $this->actingAs($user)->postJson('/__test-ai-assistant/messages', [
            'message' => 'Please help me; my key is '.$secret,
        ]);
        $response->assertOk();

        $conversation = \DB::table('ai_assistant_conversations')->where('user_id', $user->id)->first();
        $message = \DB::table('ai_assistant_messages')->where('conversation_id', $conversation->id)->where('message_role', 'user')->first();
        $this->assertStringNotContainsString($secret, $conversation->title);
        $this->assertStringContainsString('[REDACTED_SECRET]', $conversation->title);
        $this->assertStringNotContainsString($secret, $message->content);
        Http::assertSent(function ($request) use ($secret): bool {
            $userPrompt = $request->data()['messages'][1]['content'] ?? '';
            return str_contains($userPrompt, '[REDACTED_SECRET]') && ! str_contains($userPrompt, $secret);
        });
    }

    public function test_accountant_is_only_explicitly_mapped_to_support_staff_assistant(): void
    {
        $this->assertSame('support_staff', AssistantSettings::roleForUser('accountant'));
        $this->assertNull(AssistantSettings::roleForUser('unknown-role'));
    }

    public function test_optional_plugin_catalogue_entry_is_not_installed_or_enabled_by_default(): void
    {
        $catalog = app(ModuleRegistry::class)->catalog();
        $this->assertArrayHasKey('universal_ai_assistant', $catalog);
        $this->assertFalse($catalog['universal_ai_assistant']['installed']);
        $this->assertFalse($catalog['universal_ai_assistant']['enabled']);
        $this->assertSame('not_installed', $catalog['universal_ai_assistant']['status']);
    }

    public function test_optional_plugin_cannot_be_enabled_from_the_module_toggle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ModuleRegistry::class)->setEnabled('universal_ai_assistant', true);
    }


    public function test_removing_ai_plugin_code_preserves_conversation_history(): void
    {
        $user = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $conversationId = \DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $user->id,
            'assistant_role' => 'student',
            'title' => 'Keep after uninstall',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $relative = 'plugins/test/ai-data-retention/1.0.0';
        $directory = base_path($relative);
        \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
        file_put_contents($directory.'/entry.php', '<?php // test-only package');

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'test.ai-data-retention',
            'name' => 'AI Retention Test',
            'version' => '1.0.0',
            'type' => 'ai',
            'status' => 'inactive',
            'manifest' => [],
            'package_path' => $relative,
        ])->save();

        app(\App\Services\Plugins\PluginLifecycle::class)->transition('test.ai-data-retention', 'uninstalled');
        app(\App\Services\Plugins\PluginPackageRemover::class)->remove($plugin->fresh());

        $this->assertDatabaseHas('ai_assistant_conversations', [
            'id' => $conversationId,
            'title' => 'Keep after uninstall',
        ]);
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function test_assistant_conversation_schema_retains_records_when_plugin_is_inactive(): void
    {
        $user = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $conversationId = \DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $user->id, 'assistant_role' => 'student', 'title' => 'Retained history',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.universal-ai-assistant', 'name' => 'Universal AI Assistant',
            'version' => '1.0.0', 'type' => 'ai', 'status' => 'inactive', 'manifest' => [],
        ])->save();

        $this->assertDatabaseHas('ai_assistant_conversations', ['id' => $conversationId, 'title' => 'Retained history']);
    }
}
