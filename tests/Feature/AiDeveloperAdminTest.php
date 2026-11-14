<?php

namespace Tests\Feature;

use App\Models\AiDeveloperTask;
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

    public function test_admin_can_see_only_their_own_ai_task_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        AiDeveloperTask::create([
            'user_id' => $admin->id,
            'title' => 'My private diagnostic',
            'instruction' => 'A sanitized private instruction.',
            'status' => 'completed',
        ]);
        AiDeveloperTask::create([
            'user_id' => $otherAdmin->id,
            'title' => 'Another admin private diagnostic',
            'instruction' => 'Another sanitized private instruction.',
            'status' => 'completed',
        ]);

        $this->actingAs($admin)->get(route('admin.ai-developer.index'))
            ->assertOk()
            ->assertSee('My private diagnostic')
            ->assertDontSee('Another admin private diagnostic');
    }

    public function test_admin_can_request_a_diagnostic_and_secrets_are_redacted_before_storage_and_forwarding(): void
    {
        config([
            'services.ai_developer.api_key' => 'test-key',
            'services.ai_developer.base_url' => 'https://ai.example.test/v1',
            'services.ai_developer.allowed_hosts' => ['ai.example.test'],
            'services.ai_developer.model' => 'test-model',
        ]);
        Http::fake([
            'ai.example.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'সমস্যার কারণ student@example.com এবং 01712345678; route configuration.']]],
            ]),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $rawInstruction = 'Student login error. Contact student@example.com at 01712345678. Bearer abc.def.ghi and sk-1234567890abcdefghijklmnop should be hidden.';

        $this->actingAs($admin)->post(route('admin.ai-developer.analyze'), [
            'title' => 'Student login problem',
            'instruction' => $rawInstruction,
        ])->assertRedirect(route('admin.ai-developer.index'));

        $this->assertDatabaseHas('ai_developer_tasks', [
            'user_id' => $admin->id,
            'title' => 'Student login problem',
            'status' => 'completed',
        ]);
        $task = AiDeveloperTask::query()->where('user_id', $admin->id)->latest()->firstOrFail();
        $this->assertSame('Student login error. Contact [REDACTED_EMAIL] at [REDACTED_PHONE]. Bearer [REDACTED] and [REDACTED_SECRET] should be hidden.', $task->instruction);
        $this->assertSame('সমস্যার কারণ [REDACTED_EMAIL] এবং [REDACTED_PHONE]; route configuration.', $task->response);
        $this->assertDatabaseMissing('ai_developer_tasks', ['instruction' => $rawInstruction]);

        Http::assertSent(function ($request) {
            $message = $request['messages'][1]['content'];

            return str_contains($request->url(), '/chat/completions')
                && $request['model'] === 'test-model'
                && str_contains($message, '[REDACTED]')
                && str_contains($message, '[REDACTED_SECRET]')
                && !str_contains($message, 'abc.def.ghi')
                && !str_contains($message, 'sk-1234567890abcdefghijklmnop')
                && !str_contains($message, 'student@example.com')
                && !str_contains($message, '01712345678');
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

    public function test_ai_provider_rejects_http_and_private_network_urls_without_sending_requests(): void
    {
        config([
            'services.ai_developer.api_key' => 'test-key',
            'services.ai_developer.base_url' => 'http://127.0.0.1:8000/v1',
            'services.ai_developer.allowed_hosts' => ['127.0.0.1'],
            'services.ai_developer.model' => 'test-model',
        ]);
        Http::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.ai-developer.analyze'), [
            'title' => 'Unsafe provider configuration',
            'instruction' => 'Please inspect this provider configuration safely.',
        ])->assertRedirect(route('admin.ai-developer.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('ai_developer_tasks', [
            'user_id' => $admin->id,
            'status' => 'failed',
            'error_message' => 'The configured AI provider URL is not HTTPS or is not in the server-side allowed-host list.',
        ]);
        Http::assertNothingSent();
    }


    public function test_ai_provider_rejects_unapproved_hosts_ip_literals_credentials_query_fragment_and_nonstandard_ports(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        Http::fake();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $unsafeUrls = [
            'https://unapproved.example.test/v1',
            'https://127.0.0.1/v1',
            'https://192.168.1.10/v1',
            'https://user:password@ai.example.test/v1',
            'https://ai.example.test/v1?token=secret',
            'https://ai.example.test/v1#fragment',
            'https://ai.example.test:8443/v1',
            'https://localhost/v1',
            'https://service.internal/v1',
        ];

        foreach ($unsafeUrls as $url) {
            config([
                'services.ai_developer.api_key' => 'test-key',
                'services.ai_developer.base_url' => $url,
                'services.ai_developer.allowed_hosts' => ['ai.example.test'],
                'services.ai_developer.model' => 'test-model',
            ]);

            $this->actingAs($admin)->post(route('admin.ai-developer.analyze'), [
                'title' => 'Provider URL validation',
                'instruction' => 'Please validate this provider URL safely.',
            ])->assertRedirect(route('admin.ai-developer.index'))
                ->assertSessionHas('error');
        }

        $this->assertDatabaseCount('ai_developer_tasks', count($unsafeUrls));
        $this->assertSame(
            count($unsafeUrls),
            AiDeveloperTask::query()->where('status', 'failed')->count()
        );
        Http::assertNothingSent();
    }

    public function test_provider_redirect_is_not_followed_and_is_recorded_as_failure(): void
    {
        config([
            'services.ai_developer.api_key' => 'test-key',
            'services.ai_developer.base_url' => 'https://ai.example.test/v1',
            'services.ai_developer.allowed_hosts' => ['ai.example.test'],
            'services.ai_developer.model' => 'test-model',
        ]);
        Http::fake([
            'ai.example.test/*' => Http::response([], 302, ['Location' => 'https://redirected.example.test/steal']),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.ai-developer.analyze'), [
            'title' => 'Provider redirect test',
            'instruction' => 'Please verify that provider redirects are rejected.',
        ])->assertRedirect(route('admin.ai-developer.index'));

        $this->assertDatabaseHas('ai_developer_tasks', [
            'user_id' => $admin->id,
            'status' => 'failed',
            'error_message' => 'The AI request failed. Check the provider configuration and application logs.',
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'ai.example.test'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'redirected.example.test'));
    }

    public function test_provider_non_success_and_empty_responses_fail_without_persisting_an_answer(): void
    {
        config([
            'services.ai_developer.api_key' => 'test-key',
            'services.ai_developer.base_url' => 'https://ai.example.test/v1',
            'services.ai_developer.allowed_hosts' => ['ai.example.test'],
            'services.ai_developer.model' => 'test-model',
        ]);
        Http::fakeSequence()
            ->push(['error' => ['message' => 'provider unavailable']], 503)
            ->push(['choices' => [['message' => ['content' => '   ']]]], 200);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        foreach (['Provider unavailable', 'Empty provider response'] as $title) {
            $this->actingAs($admin)->post(route('admin.ai-developer.analyze'), [
                'title' => $title,
                'instruction' => 'Please diagnose this provider response safely.',
            ])->assertRedirect(route('admin.ai-developer.index'));
        }

        $this->assertDatabaseHas('ai_developer_tasks', [
            'title' => 'Provider unavailable',
            'status' => 'failed',
            'response' => null,
        ]);
        $this->assertDatabaseHas('ai_developer_tasks', [
            'title' => 'Empty provider response',
            'status' => 'failed',
            'response' => null,
        ]);
    }


}
