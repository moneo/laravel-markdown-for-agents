<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Moneo\MarkdownForAgents\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('markdown-for-agents')->get('/test-page', function () {
            return response('<html><body><h1>Hello World</h1><p>Some content here.</p></body></html>')
                ->header('Content-Type', 'text/html; charset=UTF-8');
        });

        Route::middleware('markdown-for-agents')->get('/json-endpoint', function () {
            return response()->json(['message' => 'hello']);
        });
    }

    #[Test]
    public function it_converts_html_to_markdown_when_accept_header_present(): void
    {
        $response = $this->get('/test-page', ['Accept' => 'text/markdown']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
        $response->assertHeader('Vary', 'accept');
        $response->assertHeader('x-markdown-tokens');
        $response->assertHeader('Content-Signal', 'ai-train=yes, search=yes, ai-input=yes');

        $content = $response->getContent();
        $this->assertStringContainsString('Hello World', $content);
        $this->assertStringNotContainsString('<h1>', $content);
    }

    #[Test]
    public function it_returns_html_when_accept_header_absent(): void
    {
        $response = $this->get('/test-page', ['Accept' => 'text/html']);

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<h1>', $response->getContent());
    }

    #[Test]
    public function it_does_not_convert_non_html_responses(): void
    {
        $response = $this->get('/json-endpoint', ['Accept' => 'text/markdown']);

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function it_includes_correct_token_count(): void
    {
        $response = $this->get('/test-page', ['Accept' => 'text/markdown']);

        $tokens = (int) $response->headers->get('x-markdown-tokens');
        $this->assertGreaterThan(0, $tokens);
    }
}
