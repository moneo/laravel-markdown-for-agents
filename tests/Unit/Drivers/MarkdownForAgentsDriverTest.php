<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit\Drivers;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Moneo\MarkdownForAgents\Drivers\MarkdownForAgentsDriver;
use Moneo\MarkdownForAgents\Exceptions\ConversionFailedException;
use Moneo\MarkdownForAgents\Exceptions\UnsupportedFormatException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MarkdownForAgentsDriverTest extends TestCase
{
    private function createDriver(array $responses, array &$history = []): MarkdownForAgentsDriver
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new MarkdownForAgentsDriver(
            config: ['drivers' => ['agents' => ['timeout' => 10, 'retry' => 0]]],
            handler: $stack,
        );
    }

    #[Test]
    public function it_converts_url_successfully(): void
    {
        $markdown = "# Hello World\n\nSome content here.";
        $history = [];
        $driver = $this->createDriver([
            new Response(200, [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'x-markdown-tokens' => '725',
                'Content-Signal' => 'ai-train=yes, search=yes, ai-input=yes',
            ], $markdown),
        ], $history);

        $result = $driver->convertUrl('https://example.com');

        $this->assertSame('https://example.com', $result->name);
        $this->assertSame($markdown, $result->markdown);
        $this->assertSame('text/markdown', $result->mimeType);
        $this->assertSame(725, $result->tokens);
        $this->assertSame(['ai-train' => 'yes', 'search' => 'yes', 'ai-input' => 'yes'], $result->contentSignals);
        $this->assertSame('agents', $result->driver);
        $this->assertFalse($result->fromCache);
    }

    #[Test]
    public function it_falls_back_to_token_estimator_when_header_missing(): void
    {
        $markdown = str_repeat('a', 100);
        $driver = $this->createDriver([
            new Response(200, [
                'Content-Type' => 'text/markdown; charset=utf-8',
            ], $markdown),
        ]);

        $result = $driver->convertUrl('https://example.com');

        $this->assertSame(25, $result->tokens);
        $this->assertNull($result->contentSignals);
    }

    #[Test]
    public function it_throws_conversion_failed_when_html_returned(): void
    {
        $driver = $this->createDriver([
            new Response(200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ], '<html><body>Hello</body></html>'),
        ]);

        $this->expectException(ConversionFailedException::class);
        $this->expectExceptionMessageMatches('/Expected content type/');

        $driver->convertUrl('https://example.com');
    }

    #[Test]
    public function it_does_not_send_auth_header(): void
    {
        $history = [];
        $driver = $this->createDriver([
            new Response(200, ['Content-Type' => 'text/markdown'], '# Test'),
        ], $history);

        $driver->convertUrl('https://example.com');

        $this->assertCount(1, $history);
        $this->assertFalse($history[0]['request']->hasHeader('Authorization'));
    }

    #[Test]
    public function it_sends_accept_text_markdown_header(): void
    {
        $history = [];
        $driver = $this->createDriver([
            new Response(200, ['Content-Type' => 'text/markdown'], '# Test'),
        ], $history);

        $driver->convertUrl('https://example.com');

        $this->assertSame('text/markdown', $history[0]['request']->getHeaderLine('Accept'));
    }

    #[Test]
    public function convert_html_throws_unsupported(): void
    {
        $driver = $this->createDriver([]);

        $this->expectException(UnsupportedFormatException::class);

        $driver->convertHtml('<p>Hello</p>');
    }

    #[Test]
    public function convert_file_throws_unsupported(): void
    {
        $driver = $this->createDriver([]);

        $this->expectException(UnsupportedFormatException::class);

        $driver->convertFile('/path/to/file.pdf');
    }

    #[Test]
    public function convert_files_throws_unsupported(): void
    {
        $driver = $this->createDriver([]);

        $this->expectException(UnsupportedFormatException::class);

        $driver->convertFiles(['/path/to/file.pdf']);
    }

    #[Test]
    public function supported_formats_returns_empty_array(): void
    {
        $driver = $this->createDriver([]);

        $this->assertSame([], $driver->supportedFormats());
    }
}
