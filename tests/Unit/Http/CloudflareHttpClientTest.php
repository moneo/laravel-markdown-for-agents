<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit\Http;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Moneo\MarkdownForAgents\Exceptions\AuthenticationException;
use Moneo\MarkdownForAgents\Exceptions\MarkdownForAgentsException;
use Moneo\MarkdownForAgents\Exceptions\RateLimitException;
use Moneo\MarkdownForAgents\Http\CloudflareHttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CloudflareHttpClientTest extends TestCase
{
    private function createClient(array $responses, array $config = [], ?string $apiToken = null): CloudflareHttpClient
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);

        return new CloudflareHttpClient($config, $apiToken, $handler);
    }

    #[Test]
    public function it_performs_a_successful_get_request(): void
    {
        $client = $this->createClient([
            new Response(200, [], '# Hello'),
        ]);

        $response = $client->get('https://example.com');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('# Hello', (string) $response->getBody());
    }

    #[Test]
    public function it_performs_a_successful_post_json_request(): void
    {
        $client = $this->createClient([
            new Response(200, ['Content-Type' => 'application/json'], '{"success":true}'),
        ]);

        $response = $client->postJson('https://api.cloudflare.com/test', ['url' => 'https://example.com']);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function it_performs_a_successful_post_multipart_request(): void
    {
        $client = $this->createClient([
            new Response(200, [], '[{"name":"test.pdf"}]'),
        ]);

        $response = $client->postMultipart('https://api.cloudflare.com/test', [
            ['name' => 'files', 'contents' => 'pdf content', 'filename' => 'test.pdf'],
        ]);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function it_adds_authorization_header_when_token_is_provided(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'ok'),
        ]);

        $history = [];
        $handler = HandlerStack::create($mock);
        $handler->push(\GuzzleHttp\Middleware::history($history));

        $client = new CloudflareHttpClient(['retry' => 0], 'test-token-123', $handler);
        $client->get('https://api.cloudflare.com/test');

        $this->assertCount(1, $history);
        $this->assertSame(
            'Bearer test-token-123',
            $history[0]['request']->getHeaderLine('Authorization'),
        );
    }

    #[Test]
    public function it_does_not_add_authorization_header_when_no_token(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'ok'),
        ]);

        $history = [];
        $handler = HandlerStack::create($mock);
        $handler->push(\GuzzleHttp\Middleware::history($history));

        $client = new CloudflareHttpClient(['retry' => 0], null, $handler);
        $client->get('https://example.com');

        $this->assertCount(1, $history);
        $this->assertFalse($history[0]['request']->hasHeader('Authorization'));
    }

    #[Test]
    public function it_throws_authentication_exception_on_401(): void
    {
        $client = $this->createClient([
            new Response(401, [], '{"error":"unauthorized"}'),
        ], ['retry' => 0]);

        $this->expectException(AuthenticationException::class);

        $client->get('https://api.cloudflare.com/test');
    }

    #[Test]
    public function it_throws_rate_limit_exception_on_429_after_retries_exhausted(): void
    {
        $client = $this->createClient([
            new Response(429, ['Retry-After' => '10'], ''),
            new Response(429, ['Retry-After' => '10'], ''),
        ], ['retry' => 1, 'retry_delay' => 1]);

        $this->expectException(RateLimitException::class);

        $client->get('https://api.cloudflare.com/test');
    }

    #[Test]
    public function it_retries_on_5xx_errors(): void
    {
        $client = $this->createClient([
            new Response(500, [], 'error'),
            new Response(500, [], 'error'),
            new Response(200, [], 'success'),
        ], ['retry' => 3, 'retry_delay' => 1]);

        $response = $client->get('https://api.cloudflare.com/test');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', (string) $response->getBody());
    }

    #[Test]
    public function it_retries_on_429_then_succeeds(): void
    {
        $client = $this->createClient([
            new Response(429, ['Retry-After' => '1'], ''),
            new Response(200, [], '{"success":true}'),
        ], ['retry' => 2, 'retry_delay' => 1]);

        $response = $client->get('https://api.cloudflare.com/test');

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function rate_limit_exception_includes_retry_after(): void
    {
        $client = $this->createClient([
            new Response(429, ['Retry-After' => '30'], ''),
            new Response(429, ['Retry-After' => '30'], ''),
        ], ['retry' => 1, 'retry_delay' => 1]);

        try {
            $client->get('https://api.cloudflare.com/test');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(30, $e->retryAfter);
            $this->assertStringContainsString('30 seconds', $e->getMessage());
        }
    }

    #[Test]
    public function it_wraps_connection_errors_in_markdown_exception(): void
    {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException(
                'Connection timed out',
                new \GuzzleHttp\Psr7\Request('GET', 'https://example.com'),
            ),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new CloudflareHttpClient(['retry' => 0], null, $handler);

        $this->expectException(MarkdownForAgentsException::class);
        $this->expectExceptionMessageMatches('/Connection failed/');

        $client->get('https://example.com');
    }

    #[Test]
    public function it_uses_configured_timeout(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'ok'),
        ]);

        $history = [];
        $handler = HandlerStack::create($mock);
        $handler->push(\GuzzleHttp\Middleware::history($history));

        $client = new CloudflareHttpClient(['timeout' => 90, 'retry' => 0], null, $handler);
        $client->get('https://example.com');

        $this->assertCount(1, $history);
    }

    #[Test]
    public function it_merges_custom_headers_with_defaults(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'ok'),
        ]);

        $history = [];
        $handler = HandlerStack::create($mock);
        $handler->push(\GuzzleHttp\Middleware::history($history));

        $client = new CloudflareHttpClient(['retry' => 0], 'my-token', $handler);
        $client->get('https://example.com', ['Accept' => 'text/markdown']);

        $request = $history[0]['request'];
        $this->assertSame('Bearer my-token', $request->getHeaderLine('Authorization'));
        $this->assertSame('text/markdown', $request->getHeaderLine('Accept'));
    }
}
