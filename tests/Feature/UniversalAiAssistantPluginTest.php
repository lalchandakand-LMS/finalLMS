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
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantRequestLimit.php');
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
        if (! Schema::hasTable('ai_assistant_daily_budgets')) {
            $budgetMigration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_17_090000_create_ai_assistant_token_budget_tables.php');
            $budgetMigration->up();
        }

        if (! Schema::hasTable('ai_assistant_user_daily_requests')) {
            $requestMigration = require base_path('plugins/aec/universal-ai-assistant/1.0.0/migrations/2026_11_17_100000_create_ai_assistant_user_daily_requests.php');
            $requestMigration->up();
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

    public function test_daily_token_budget_reservations_prevent_oversubscription_and_release_or_settle_capacity(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'General guidance only.'],
            ['role' => 'user', 'content' => 'Practise English with me.'],
        ];

        $reservation = AssistantTokenBudget::reserve(1500, 1200, $messages);
        $this->assertNotNull($reservation);
        $this->assertLessThanOrEqual(1500, $reservation['reserved_tokens']);
        $this->assertNull(AssistantTokenBudget::reserve($reservation['reserved_tokens'], 1200, $messages));

        AssistantTokenBudget::release($reservation['id']);
        $this->assertDatabaseHas('ai_assistant_token_reservations', [
            'id' => $reservation['id'],
            'status' => 'released',
        ]);

        $next = AssistantTokenBudget::reserve(1500, 1200, $messages);
        $this->assertNotNull($next);
        AssistantTokenBudget::settle($next['id'], 42);

        $this->assertDatabaseHas('ai_assistant_daily_budgets', [
            'budget_date' => today()->toDateString(),
            'used_tokens' => 42,
            'reserved_tokens' => 0,
        ]);
    }

    public function test_expired_token_reservations_are_reclaimed_before_new_requests(): void
    {
        $messages = [['role' => 'user', 'content' => 'Hello']];
        $reservation = AssistantTokenBudget::reserve(1000, 800, $messages);
        $this->assertNotNull($reservation);

        \DB::table('ai_assistant_token_reservations')
            ->where('id', $reservation['id'])
            ->update(['expires_at' => now()->subMinute()]);

        $replacement = AssistantTokenBudget::reserve(1000, 800, $messages);

        $this->assertNotNull($replacement);
        $this->assertDatabaseHas('ai_assistant_token_reservations', [
            'id' => $reservation['id'],
            'status' => 'expired',
        ]);
    }

    public function test_abandoned_reservations_older_than_thirty_days_are_pruned(): void
    {
        $reservationId = (string) \Illuminate\Support\Str::uuid();
        $oldDate = today()->subDays(31)->toDateString();

        \DB::table('ai_assistant_daily_budgets')->insert([
            'budget_date' => $oldDate,
            'used_tokens' => 25,
            'reserved_tokens' => 75,
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);
        \DB::table('ai_assistant_token_reservations')->insert([
            'id' => $reservationId,
            'budget_date' => $oldDate,
            'reserved_tokens' => 75,
            'status' => 'reserved',
            'expires_at' => now()->subDays(31),
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);

        $replacement = AssistantTokenBudget::reserve(1000, 800, [
            ['role' => 'user', 'content' => 'A new day, a new lesson.'],
        ]);

        $this->assertNotNull($replacement);
        $this->assertDatabaseMissing('ai_assistant_token_reservations', ['id' => $reservationId]);
        $this->assertDatabaseHas('ai_assistant_daily_budgets', [
            'budget_date' => $oldDate,
            'used_tokens' => 25,
        ]);
    }

    public function test_daily_request_limit_rejects_before_creating_a_conversation_or_calling_provider(): void
    {
        AssistantSettings::save([
            'model' => 'gpt-4o-mini', 'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.4, 'max_tokens' => 800, 'global_enabled' => true,
            'role_enabled' => array_replace(array_fill_keys(AssistantSettings::ROLES, false), ['student' => true]),
            'daily_request_limit' => 1, 'daily_token_budget' => 50000, 'retention_days' => 365,
        ], 'test-secret-provider-key', false);

        $user = User::factory()->create(['role' => 'student']);
        \DB::table('ai_assistant_user_daily_requests')->insert([
            'user_id' => $user->id,
            'request_date' => today()->toDateString(),
            'request_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake();
        Route::post('/__test-ai-assistant/request-limit', [\Aloms\UniversalAiAssistant\AssistantController::class, 'send']);

        $this->actingAs($user)->postJson('/__test-ai-assistant/request-limit', [
            'message' => 'Help me practise English.',
        ])->assertStatus(429);

        $this->assertDatabaseMissing('ai_assistant_conversations', ['user_id' => $user->id]);
        Http::assertNothingSent();
    }

    public function test_insufficient_daily_budget_rejects_before_any_provider_request(): void
    {
        AssistantSettings::save([
            'model' => 'gpt-4o-mini', 'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.4, 'max_tokens' => 800, 'global_enabled' => true,
            'role_enabled' => array_replace(array_fill_keys(AssistantSettings::ROLES, false), ['student' => true]),
            'daily_request_limit' => 20, 'daily_token_budget' => 5, 'retention_days' => 365,
        ], 'test-secret-provider-key', false);

        app()->instance(AiDeveloperProviderEndpoint::class, new class extends AiDeveloperProviderEndpoint {
            public function curlOptionsFor(string $url): array { return []; }
        });
        Http::fake();

        $user = User::factory()->create(['role' => 'student']);
        Route::post('/__test-ai-assistant/budget-messages', [\Aloms\UniversalAiAssistant\AssistantController::class, 'send']);

        $this->actingAs($user)->postJson('/__test-ai-assistant/budget-messages', [
            'message' => 'Help me practise English.',
        ])->assertStatus(429);

        Http::assertNothingSent();
    }

    public function test_each_role_assistant_switch_is_independently_mapped_and_default_deny(): void
    {
        $roleMap = [
            'admin' => 'admin',
            'branch_manager' => 'branch_manager',
            'teacher' => 'teacher',
            'student' => 'student',
            'guardian' => 'guardian',
            'accountant' => 'support_staff',
        ];

        foreach ($roleMap as $userRole => $assistantRole) {
            $switches = array_fill_keys(AssistantSettings::ROLES, false);
            $switches[$assistantRole] = true;
            AssistantSettings::save([
                'model' => 'gpt-4o-mini', 'base_url' => 'https://api.openai.com/v1',
                'temperature' => 0.4, 'max_tokens' => 800, 'global_enabled' => true,
                'role_enabled' => $switches, 'daily_request_limit' => 20,
                'daily_token_budget' => 50000, 'retention_days' => 365,
            ], null, false);

            $this->assertSame($assistantRole, AssistantSettings::roleForUser($userRole));
            foreach (AssistantSettings::ROLES as $candidate) {
                $this->assertSame($candidate === $assistantRole, AssistantSettings::record()['role_enabled'][$candidate]);
            }
        }
    }

    public function test_student_cannot_append_to_another_students_conversation(): void
    {
        AssistantSettings::save([
            'model' => 'gpt-4o-mini', 'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.4, 'max_tokens' => 800, 'global_enabled' => true,
            'role_enabled' => array_replace(array_fill_keys(AssistantSettings::ROLES, false), ['student' => true]),
            'daily_request_limit' => 20, 'daily_token_budget' => 50000, 'retention_days' => 365,
        ], 'test-secret-provider-key', false);

        $owner = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $conversationId = \DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $owner->id, 'assistant_role' => 'student', 'title' => 'Private conversation',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Route::post('/__test-ai-assistant/isolation-messages', [\Aloms\UniversalAiAssistant\AssistantController::class, 'send']);

        $this->actingAs($otherStudent)->postJson('/__test-ai-assistant/isolation-messages', [
            'message' => 'Append to this conversation',
            'conversation_id' => $conversationId,
        ])->assertNotFound();

        $this->assertDatabaseCount('ai_assistant_messages', 0);
    }

    public function test_provider_failure_releases_token_reservation_and_does_not_charge_usage(): void
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
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['error' => 'provider unavailable'], 503)]);

        $user = User::factory()->create(['role' => 'student']);
        Route::post('/__test-ai-assistant/failure-messages', [\Aloms\UniversalAiAssistant\AssistantController::class, 'send']);

        $this->actingAs($user)->postJson('/__test-ai-assistant/failure-messages', [
            'message' => 'Help me practise English.',
        ])->assertStatus(502);

        $this->assertDatabaseHas('ai_assistant_token_reservations', ['status' => 'released']);
        $this->assertDatabaseHas('ai_assistant_daily_budgets', [
            'budget_date' => today()->toDateString(),
            'used_tokens' => 0,
            'reserved_tokens' => 0,
        ]);
    }


    public function test_assistant_conversations_are_isolated_between_roles_even_for_the_same_user(): void
    {
        AssistantSettings::save([
            'model' => 'gpt-4o-mini', 'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.4, 'max_tokens' => 800, 'global_enabled' => true,
            'role_enabled' => array_replace(array_fill_keys(AssistantSettings::ROLES, false), [
                'teacher' => true,
                'student' => true,
            ]),
            'daily_request_limit' => 20, 'daily_token_budget' => 50000, 'retention_days' => 365,
        ], 'test-secret-provider-key', false);

        $user = User::factory()->create(['role' => 'teacher']);
        $conversationId = \\DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $user->id,
            'assistant_role' => 'teacher',
            'title' => 'Teacher-only context',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A role change must not make a conversation created in the previous role visible.
        $user->forceFill(['role' => 'student'])->save();
        Route::post('/__test-ai-assistant/cross-role-messages', [\\Aloms\\UniversalAiAssistant\\AssistantController::class, 'send']);

        $this->actingAs($user)->postJson('/__test-ai-assistant/cross-role-messages', [
            'message' => 'Continue this conversation',
            'conversation_id' => $conversationId,
        ])->assertNotFound();

        $this->assertDatabaseCount('ai_assistant_messages', 0);
    }

    public function test_every_role_prompt_explicitly_denies_private_lms_data_and_consequential_actions(): void
    {
        foreach (AssistantSettings::ROLES as $role) {
            $prompt = AssistantSettings::rolePrompt($role);

            $this->assertStringContainsString('GENERAL GUIDANCE ONLY', $prompt);
            $this->assertStringContainsString('no access to LMS databases', $prompt);
            $this->assertStringContainsString('Never claim to have looked up', $prompt);
            $this->assertStringContainsString('Do not perform consequential actions', $prompt);
        }
    }


}
