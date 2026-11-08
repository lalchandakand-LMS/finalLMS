<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiDeveloperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_ai_developer(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)->get(route('admin.ai-developer.index'))->assertForbidden();
    }

    public function test_admin_can_open_ai_developer_workspace(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.ai-developer.index'))
            ->assertOk()
            ->assertSee('AI Developer');
    }

    public function test_admin_can_request_a_diagnostic_and_secrets_are_redacted_before_storage_and_forwarding(): void
    {
        config([
            'services.ai_developer.api_key' => 'test-key',
            'services.ai_developer.base_url' => 'https://ai.example.test/v1',
            'services.ai_developer.model' => 'test-model',
        ]);
        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'সমস্যার সম্ভাব্য কারণ হলো route configuration.']]],
            ]),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $rawInstruction = 'Student login error. Bearer abc.def.ghi and sk-1234567890abcdefghijklmnop should be hidden.';

        $this->actingAs($admin)->post(route('admin.ai-developer.analyze'), [
            'title' => 'Student login problem',
            'instruction' => $rawInstruction,
        ])->assertRedirect(route('admin.ai-developer.index'));

        $this->assertDatabaseHas('ai_developer_tasks', [
            'user_id' => $admin->id,
            'title' => 'Student login problem',
            'status' => 'completed',
            'instruction' => 'Student login error. Bearer [REDACTED] and [REDACTED_SECRET] should be hidden.',
            'response' => 'সমস্যার সম্ভাব্য কারণ হলো route configuration.',
        ]);
        $this->assertDatabaseMissing('ai_developer_tasks', ['instruction' => $rawInstruction]);

        Http::assertSent(function ($request) {
            $message = $request['messages'][1]['content'];

            return str_contains($request->url(), '/chat/completions')
                && $request['model'] === 'test-model'
                && str_contains($message, '[REDACTED]')
                && str_contains($message, '[REDACTED_SECRET]')
                && !str_contains($message, 'abc.def.ghi')
                && !str_contains($message, 'sk-1234567890abcdefghijklmnop');
        });
    }

    public function test_unconfigured_provider_does_not_send_an_external_request(): void
    {
        config([
            'services.ai_developer.api_key' => null,
            'services.ai_developer.base_url' => 'https://ai.example.test/v1',
            'services.ai_developer.model' => 'test-model',
        ]);
        Http::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.ai-developer.analyze'), [
            'title' => 'Test setup',
            'instruction' => 'Please diagnose this configuration issue safely.',
        ])->assertRedirect(route('admin.ai-developer.index'));

        $this->assertDatabaseHas('ai_developer_tasks', [
            'user_id' => $admin->id,
            'status' => 'needs_configuration',
        ]);
        Http::assertNothingSent();
    }
}
