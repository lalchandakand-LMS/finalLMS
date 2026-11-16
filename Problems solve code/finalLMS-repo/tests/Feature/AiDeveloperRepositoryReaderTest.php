<?php

namespace Tests\Feature;

use App\Services\AiDeveloperRepositoryReader;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AiDeveloperRepositoryReaderTest extends TestCase
{
    public function test_repository_context_is_disabled_by_default_and_makes_no_request(): void
    {
        config(['services.ai_developer.repository_context_enabled' => false]);
        Http::fake();

        $this->assertSame([], app(AiDeveloperRepositoryReader::class)->readConfiguredFiles());
        Http::assertNothingSent();
    }

    public function test_reader_fetches_only_allow_listed_file_and_redacts_common_secrets_and_phone_numbers(): void
    {
        config([
            'services.ai_developer.repository_context_enabled' => true,
            'services.ai_developer.github_token' => 'github-test-token',
            'services.ai_developer.github_repository' => 'lalchandakand-LMS/finalLMS',
            'services.ai_developer.github_read_ref' => 'main',
            'services.ai_developer.allowed_repository_paths' => ['app/Example.php'],
        ]);

        $source = '<?php // Bearer abc.def.ghi sk-1234567890abcdefghijklmnop 01712345678 admin@example.com password => \'super-secret\'';
        Http::fake([
            'api.github.com/*' => Http::response([
                'type' => 'file',
                'encoding' => 'base64',
                'content' => base64_encode($source),
            ]),
        ]);

        $files = app(AiDeveloperRepositoryReader::class)->readConfiguredFiles();

        $this->assertArrayHasKey('app/Example.php', $files);
        $this->assertStringNotContainsString('abc.def.ghi', $files['app/Example.php']);
        $this->assertStringNotContainsString('sk-1234567890abcdefghijklmnop', $files['app/Example.php']);
        $this->assertStringNotContainsString('01712345678', $files['app/Example.php']);
        $this->assertStringNotContainsString('admin@example.com', $files['app/Example.php']);
        $this->assertStringNotContainsString('super-secret', $files['app/Example.php']);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $files['app/Example.php']);
        $this->assertStringContainsString('[REDACTED_SECRET]', $files['app/Example.php']);
        $this->assertStringContainsString('[REDACTED_PHONE]', $files['app/Example.php']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/repos/lalchandakand-LMS/finalLMS/contents/app/Example.php')
            && str_contains($request->url(), 'ref=main'));
    }

    public function test_reader_rejects_sensitive_or_traversal_paths_before_network_request(): void
    {
        config([
            'services.ai_developer.repository_context_enabled' => true,
            'services.ai_developer.github_token' => 'github-test-token',
            'services.ai_developer.github_repository' => 'lalchandakand-LMS/finalLMS',
            'services.ai_developer.github_read_ref' => 'main',
            'services.ai_developer.allowed_repository_paths' => ['../../.env'],
        ]);
        Http::fake();

        try {
            app(AiDeveloperRepositoryReader::class)->readConfiguredFiles();
            $this->fail('Expected an unsafe path to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Repository allow-list contains an unsafe path.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_reader_enforces_per_file_size_limit(): void
    {
        config([
            'services.ai_developer.repository_context_enabled' => true,
            'services.ai_developer.github_token' => 'github-test-token',
            'services.ai_developer.github_repository' => 'lalchandakand-LMS/finalLMS',
            'services.ai_developer.github_read_ref' => 'main',
            'services.ai_developer.allowed_repository_paths' => ['app/Large.php'],
            'services.ai_developer.repository_max_file_bytes' => 1024,
        ]);
        Http::fake([
            'api.github.com/*' => Http::response([
                'type' => 'file',
                'encoding' => 'base64',
                'content' => base64_encode(str_repeat('A', 2048)),
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An allow-listed repository file exceeded the configured size limit.');

        app(AiDeveloperRepositoryReader::class)->readConfiguredFiles();
    }
}
