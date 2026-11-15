<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Plugins\PluginManifestValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class UniversalAiAssistantPackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('plugins/.source/aec/universal-ai-assistant/1.0.0/src/AssistantService.php');
        if (! Schema::hasTable('ai_assistant_settings')) {
            $migration = require base_path('plugins/.source/aec/universal-ai-assistant/1.0.0/database/migrations/2026_11_15_000001_create_ai_assistant_tables.php');
            $migration->up();
        }
    }

    public function test_source_manifest_is_valid_and_declares_all_supported_roles(): void
    {
        $manifest = json_decode(file_get_contents(base_path('plugins/.source/aec/universal-ai-assistant/1.0.0/manifest.json')), true, 64, JSON_THROW_ON_ERROR);

        $this->assertSame([], app(PluginManifestValidator::class)->errors($manifest));
        $this->assertEqualsCanonicalizing(
            ['admin', 'branch', 'teacher', 'student', 'guardian', 'support_staff'],
            $manifest['portals']
        );
        $this->assertSame([], $manifest['dependencies']);
    }

    public function test_role_assistant_is_default_denied_until_admin_enables_it(): void
    {
        $this->saveSettings(['student' => false]);
        $student = User::factory()->create(['role' => 'student']);

        $this->expectException(RuntimeException::class);
        (new \Aloms\UniversalAiAssistant\AssistantService())->reply($student, 'Help me practise English.');
    }

    public function test_assistant_uses_encrypted_key_and_sends_no_private_lms_records(): void
    {
        $this->saveSettings(['student' => true]);
        $student = User::factory()->create(['role' => 'student']);
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Let us practise a short dialogue.']]],
                'usage' => ['prompt_tokens' => 22, 'completion_tokens' => 11],
            ], 200),
        ]);

        $result = (new \Aloms\UniversalAiAssistant\AssistantService())->reply($student, 'Help me practise English.');

        $stored = DB::table('ai_assistant_settings')->where('id', 1)->first();
        $this->assertNotSame('test-provider-secret', $stored->api_key_encrypted);
        $this->assertSame('test-provider-secret', Crypt::decryptString($stored->api_key_encrypted));
        $this->assertSame('Let us practise a short dialogue.', $result['reply']);
        $this->assertDatabaseHas('ai_assistant_usage', [
            'user_id' => $student->id,
            'role' => 'student',
            'total_tokens' => 33,
        ]);

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $system = $body['messages'][0]['content'] ?? '';

            return str_contains($system, 'no access to private LMS records')
                && ! str_contains(json_encode($body), 'student_profiles')
                && $body['model'] === 'gpt-4.1-mini';
        });
    }

    public function test_user_cannot_access_another_users_conversation(): void
    {
        $this->saveSettings(['student' => true]);
        $owner = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $conversationId = DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $owner->id,
            'role' => 'student',
            'title' => 'Private conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        (new \Aloms\UniversalAiAssistant\AssistantService())->reply($other, 'Show my history.', $conversationId);
    }

    public function test_conversation_retention_removes_expired_history_and_cascades_messages(): void
    {
        $this->saveSettings(['student' => true], ['retention_days' => 30]);
        $student = User::factory()->create(['role' => 'student']);
        $conversationId = DB::table('ai_assistant_conversations')->insertGetId([
            'user_id' => $student->id,
            'role' => 'student',
            'title' => 'Expired conversation',
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);
        DB::table('ai_assistant_messages')->insert([
            'conversation_id' => $conversationId,
            'user_id' => $student->id,
            'role' => 'user',
            'content' => 'Old message',
            'created_at' => now()->subDays(40),
        ]);

        (new \Aloms\UniversalAiAssistant\AssistantService())->pruneExpiredConversations();

        $this->assertDatabaseMissing('ai_assistant_conversations', ['id' => $conversationId]);
        $this->assertDatabaseMissing('ai_assistant_messages', ['conversation_id' => $conversationId]);
    }

    public function test_monthly_budget_limits_the_requested_output_tokens(): void
    {
        $this->saveSettings(['student' => true], ['monthly_token_budget' => 1000, 'max_tokens' => 8192]);
        $student = User::factory()->create(['role' => 'student']);
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'A concise answer.']]],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8],
            ], 200),
        ]);

        (new \Aloms\UniversalAiAssistant\AssistantService())->reply($student, 'Hello.');

        Http::assertSent(function ($request): bool {
            $limit = (int) ($request->data()['max_completion_tokens'] ?? 0);
            return $limit > 0 && $limit <= 1000;
        });
    }

    private function saveSettings(array $roles, array $overrides = []): void
    {
        $defaults = [
            'enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
            'api_key_encrypted' => Crypt::encryptString('test-provider-secret'),
            'temperature' => 0.7,
            'max_tokens' => 512,
            'monthly_token_budget' => null,
            'retention_days' => 365,
            'role_settings' => json_encode(array_replace([
                'admin' => false,
                'branch_manager' => false,
                'teacher' => false,
                'student' => false,
                'guardian' => false,
                'support_staff' => false,
            ], $roles), JSON_THROW_ON_ERROR),
            'data_access' => json_encode(['mode' => 'general_guidance_only', 'lms_records' => false], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('ai_assistant_settings')->insert(array_replace($defaults, $overrides, ['id' => 1]));
    }
}
