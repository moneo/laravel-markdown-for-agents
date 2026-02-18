<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit\Drivers;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Moneo\MarkdownForAgents\Drivers\BrowserRenderingDriver;
use Moneo\MarkdownForAgents\Exceptions\AuthenticationException;
use Moneo\MarkdownForAgents\Exceptions\ConversionFailedException;
use Moneo\MarkdownForAgents\Exceptions\UnsupportedFormatException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BrowserRenderingDriverTest extends TestCase
{
    private function createDriver(array $responses, array &$history = [], array $driverConfig = []): BrowserRenderingDriver
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new BrowserRenderingDriver(
            config: array_merge([
                'account_id' => 'test-account',
                'api_token' => 'test-token',
                'drivers' => ['browser' => array_merge([
                    'timeout' => 30,
                    'retry' => 0,
                    'wait_until' => 'networkidle0',
                    'user_agent' => null,
                    'reject_patterns' => [],
                ], $driverConfig)],
            ]),
            handler: $stack,
        );
    }

    #[Test]
    public function it_converts_url_successfully(): void
    {
        $driver = $this->createDriver([
            new Response(200, [], json_encode(['success' => true, 'result' => '# Hello World'])),
        ]);

        $result = $driver->convertUrl('https://example.com');

        $this->assertSame('https://example.com', $result->name);
        $this->assertSame('# Hello World', $result->markdown);
        $this->assertSame('text/markdown', $result->mimeType);
        $this->assertSame('browser', $result->driver);
        $this->assertGreaterThan(0, $result->tokens);
    }

    #[Test]
    public function it_converts_html_successfully(): void
    {
        $driver = $this->createDriver([
            new Response(200, [], json_encode(['success' => true, 'result' => '# From HTML'])),
        ]);

        $result = $driver->convertHtml('<h1>From HTML</h1>');

        $this->assertSame('html', $result->name);
        $this->assertSame('# From HTML', $result->markdown);
    }

    #[Test]
    public function it_throws_on_failure_response(): void
    {
        $driver = $this->createDriver([
            new Response(200, [], json_encode(['success' => false, 'errors' => [['message' => 'Render failed']]])),
        ]);

        $this->expectException(ConversionFailedException::class);
        $this->expectExceptionMessage('Render failed');

        $driver->convertUrl('https://example.com');
    }

    #[Test]
    public function it_includes_browser_options_in_request(): void
    {
        $history = [];
        $driver = $this->createDriver([
            new Response(200, [], json_encode(['success' => true, 'result' => '# Test'])),
        ], $history);

        $driver->convertUrl('https://example.com', [
            'waitUntil' => 'load',
            'userAgent' => 'TestBot/1.0',
            'rejectPatterns' => ['/\\.css$/'],
            'cookies' => [['name' => 'session', 'value' => 'abc', 'domain' => '.example.com']],
            'authUsername' => 'user',
            'authPassword' => 'pass',
        ]);

        $body = json_decode((string) $history[0]['request']->getBody(), true);

        $this->assertSame('https://example.com', $body['url']);
        $this->assertSame(['waitUntil' => 'load'], $body['gotoOptions']);
        $this->assertSame('TestBot/1.0', $body['userAgent']);
        $this->assertSame(['/\\.css$/'], $body['rejectRequestPattern']);
        $this->assertCount(1, $body['cookies']);
        $this->assertSame(['username' => 'user', 'password' => 'pass'], $body['authenticate']);
    }

    #[Test]
    public function it_uses_config_defaults_for_browser_options(): void
    {
        $history = [];
        $driver = $this->createDriver([
            new Response(200, [], json_encode(['success' => true, 'result' => '# Test'])),
        ], $history, ['wait_until' => 'domcontentloaded', 'user_agent' => 'ConfigBot/2.0']);

        $driver->convertUrl('https://example.com');

        $body = json_decode((string) $history[0]['request']->getBody(), true);

        $this->assertSame(['waitUntil' => 'domcontentloaded'], $body['gotoOptions']);
        $this->assertSame('ConfigBot/2.0', $body['userAgent']);
    }

    #[Test]
    public function it_sends_auth_header(): void
    {
        $history = [];
        $driver = $this->createDriver([
            new Response(200, [], json_encode(['success' => true, 'result' => '# Test'])),
        ], $history);

        $driver->convertUrl('https://example.com');

        $this->assertSame('Bearer test-token', $history[0]['request']->getHeaderLine('Authorization'));
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
    public function supported_formats_returns_empty(): void
    {
        $driver = $this->createDriver([]);

        $this->assertSame([], $driver->supportedFormats());
    }

    #[Test]
    public function it_throws_when_api_token_is_missing(): void
    {
        $this->expectException(AuthenticationException::class);

        new BrowserRenderingDriver(config: [
            'account_id' => 'test',
            'api_token' => '',
            'drivers' => ['browser' => []],
        ]);
    }
}
