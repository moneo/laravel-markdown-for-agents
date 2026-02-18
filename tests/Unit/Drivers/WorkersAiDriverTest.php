<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit\Drivers;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Moneo\MarkdownForAgents\Drivers\WorkersAiDriver;
use Moneo\MarkdownForAgents\Exceptions\AuthenticationException;
use Moneo\MarkdownForAgents\Exceptions\ConversionFailedException;
use Moneo\MarkdownForAgents\Exceptions\UnsupportedFormatException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkersAiDriverTest extends TestCase
{
    private function createDriver(array $responses, array &$history = []): WorkersAiDriver
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new WorkersAiDriver(
            config: [
                'account_id' => 'test-account',
                'api_token' => 'test-token',
                'drivers' => ['workers_ai' => ['timeout' => 30, 'retry' => 0]],
            ],
            handler: $stack,
        );
    }

    #[Test]
    public function it_converts_a_single_file(): void
    {
        $responseBody = json_encode([
            ['name' => 'doc.pdf', 'mimeType' => 'application/pdf', 'format' => 'markdown', 'tokens' => 100, 'data' => '# Document'],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'mfa_test_');
        file_put_contents($tmpFile, 'fake pdf content');

        try {
            $driver = $this->createDriver([new Response(200, [], $responseBody)]);
            $result = $driver->convertFile($tmpFile);

            $this->assertSame('doc.pdf', $result->name);
            $this->assertSame('# Document', $result->markdown);
            $this->assertSame('application/pdf', $result->mimeType);
            $this->assertSame(100, $result->tokens);
            $this->assertSame('workers_ai', $result->driver);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function it_converts_html_as_blob(): void
    {
        $responseBody = json_encode([
            ['name' => 'input.html', 'mimeType' => 'text/html', 'format' => 'markdown', 'tokens' => 50, 'data' => '# Hello'],
        ]);

        $driver = $this->createDriver([new Response(200, [], $responseBody)]);
        $result = $driver->convertHtml('<h1>Hello</h1>');

        $this->assertSame('input.html', $result->name);
        $this->assertSame('# Hello', $result->markdown);
        $this->assertSame(50, $result->tokens);
    }

    #[Test]
    public function it_converts_multiple_files_in_batch(): void
    {
        $responseBody = json_encode([
            ['name' => 'doc.pdf', 'mimeType' => 'application/pdf', 'format' => 'markdown', 'tokens' => 100, 'data' => '# Doc'],
            ['name' => 'img.png', 'mimeType' => 'image/png', 'format' => 'markdown', 'tokens' => 50, 'data' => 'An image of a cat'],
        ]);

        $tmpFile1 = tempnam(sys_get_temp_dir(), 'mfa_test_');
        $tmpFile2 = tempnam(sys_get_temp_dir(), 'mfa_test_');
        file_put_contents($tmpFile1, 'fake pdf');
        file_put_contents($tmpFile2, 'fake png');

        try {
            $driver = $this->createDriver([new Response(200, [], $responseBody)]);
            $results = $driver->convertFiles([$tmpFile1, $tmpFile2]);

            $this->assertCount(2, $results);
            $this->assertSame('doc.pdf', $results[0]->name);
            $this->assertSame('img.png', $results[1]->name);
        } finally {
            @unlink($tmpFile1);
            @unlink($tmpFile2);
        }
    }

    #[Test]
    public function it_throws_on_error_format(): void
    {
        $responseBody = json_encode([
            ['name' => 'corrupt.pdf', 'mimeType' => 'application/pdf', 'format' => 'error', 'error' => 'Failed to convert document'],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'mfa_test_');
        file_put_contents($tmpFile, 'corrupt content');

        try {
            $driver = $this->createDriver([new Response(200, [], $responseBody)]);

            $this->expectException(ConversionFailedException::class);
            $this->expectExceptionMessage('Failed to convert document');

            $driver->convertFile($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function it_fetches_supported_formats(): void
    {
        $responseBody = json_encode([
            ['extension' => '.pdf', 'mimeType' => 'application/pdf'],
            ['extension' => '.docx', 'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ]);

        $driver = $this->createDriver([new Response(200, [], $responseBody)]);
        $formats = $driver->supportedFormats();

        $this->assertCount(2, $formats);
        $this->assertSame('.pdf', $formats[0]->extension);
        $this->assertSame('application/pdf', $formats[0]->mimeType);
    }

    #[Test]
    public function it_sends_auth_header(): void
    {
        $history = [];
        $responseBody = json_encode([
            ['name' => 'input.html', 'mimeType' => 'text/html', 'format' => 'markdown', 'tokens' => 10, 'data' => '# Hi'],
        ]);

        $driver = $this->createDriver([new Response(200, [], $responseBody)], $history);
        $driver->convertHtml('<h1>Hi</h1>');

        $this->assertSame('Bearer test-token', $history[0]['request']->getHeaderLine('Authorization'));
    }

    #[Test]
    public function convert_url_throws_unsupported(): void
    {
        $driver = $this->createDriver([]);

        $this->expectException(UnsupportedFormatException::class);

        $driver->convertUrl('https://example.com');
    }

    #[Test]
    public function it_throws_when_api_token_is_missing(): void
    {
        $this->expectException(AuthenticationException::class);

        new WorkersAiDriver(config: [
            'account_id' => 'test',
            'api_token' => '',
            'drivers' => ['workers_ai' => []],
        ]);
    }
}
