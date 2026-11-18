<?php

namespace Tests\\Unit;

use App\\Support\\SafeWebsiteUrl;
use PHPUnit\\Framework\\TestCase;

class SafeWebsiteUrlTest extends TestCase
{
    public function test_only_safe_http_urls_local_paths_and_fragments_are_allowed(): void
    {
        $valid = [
            'https://example.com/courses?level=1',
            'http://example.com/',
            '/courses?level=1',
            '#contact',
        ];

        foreach ($valid as $url) {
            $this->assertSame($url, SafeWebsiteUrl::sanitize($url));
        }

        $invalid = [
            'javascript:alert(1)',
            '//evil.example/path',
            'https://user:secret@example.com/path',
            'https://example.com/path with space',
            'https://example.com\\\\@evil.example',
            'https://example.com/%5c@evil.example',
            'https://example.com/%0d%0aHeader',
            'https://',
            ' https://example.com',
            "/courses\\n",
            null,
            ['https://example.com'],
        ];

        foreach ($invalid as $url) {
            $this->assertNull(SafeWebsiteUrl::sanitize($url), is_string($url) ? $url : get_debug_type($url));
        }
    }
}
