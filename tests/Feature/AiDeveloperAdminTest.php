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

    public function test_admin_can_request_a_diagnostic_from_configured_provider(): void
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

        $this->actingAs($admin)->post(route('admin.ai-developer.analyze'), [
            'title' => 'Student login problem',
            'instruction' => 'Student login করলে 500 error আসে। কীভাবে নিরাপদে পরীক্ষা করব?',
        ])->assertRedirect(route('admin.ai-developer.index'));

        $this->assertDatabaseHas('ai_developer_tasks', [
            'user_id' => $admin->id,
            'title' => 'Student login problem',
            'status' => 'completed',
            'response' => 'সমস্যার সম্ভাব্য কারণ হলো route configuration.',
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/chat/completions')
            && $request['model'] === 'test-model');
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
