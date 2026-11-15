<?php

namespace Tests\Feature;

use Aloms\UniversalAiAssistant\AssistantSettings;
use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class UniversalAiAssistantPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('plugins/aec/universal-ai-assistant/1.0.0/src/AssistantSettings.php');
    }

    public function test_assistant_settings_are_disabled_by_default_and_private_data_access_is_denied(): void
    {
        $settings = AssistantSettings::record();
        $this->assertFalse($settings['global_enabled']);
        $this->assertSame('general_only', $settings['data_access_mode']);
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
