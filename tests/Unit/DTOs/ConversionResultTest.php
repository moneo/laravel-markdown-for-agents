<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit\DTOs;

use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConversionResultTest extends TestCase
{
    private function makeResult(
        string $markdown = '# Hello World',
        int $tokens = 100,
        ?array $contentSignals = null,
        bool $fromCache = false,
    ): ConversionResult {
        return new ConversionResult(
            name: 'https://example.com',
            markdown: $markdown,
            mimeType: 'text/markdown',
            tokens: $tokens,
            contentSignals: $contentSignals,
            driver: 'agents',
            fromCache: $fromCache,
        );
    }

    #[Test]
    public function it_stores_all_properties(): void
    {
        $result = $this->makeResult(contentSignals: ['ai-train' => 'yes']);

        $this->assertSame('https://example.com', $result->name);
        $this->assertSame('# Hello World', $result->markdown);
        $this->assertSame('text/markdown', $result->mimeType);
        $this->assertSame(100, $result->tokens);
        $this->assertSame(['ai-train' => 'yes'], $result->contentSignals);
        $this->assertSame('agents', $result->driver);
        $this->assertFalse($result->fromCache);
    }

    #[Test]
    public function to_array_returns_all_properties(): void
    {
        $result = $this->makeResult();
        $array = $result->toArray();

        $this->assertSame('https://example.com', $array['name']);
        $this->assertSame('# Hello World', $array['markdown']);
        $this->assertSame('text/markdown', $array['mimeType']);
        $this->assertSame(100, $array['tokens']);
        $this->assertNull($array['contentSignals']);
        $this->assertSame('agents', $array['driver']);
        $this->assertFalse($array['fromCache']);
    }

    #[Test]
    public function to_json_returns_valid_json(): void
    {
        $result = $this->makeResult();
        $json = $result->toJson();

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('https://example.com', $decoded['name']);
    }

    #[Test]
    public function to_json_accepts_flags(): void
    {
        $result = $this->makeResult();
        $json = $result->toJson(JSON_PRETTY_PRINT);

        $this->assertStringContainsString("\n", $json);
    }

    #[Test]
    public function save_to_writes_file(): void
    {
        $result = $this->makeResult();
        $path = sys_get_temp_dir().'/mfa_test_'.uniqid().'.md';

        try {
            $this->assertTrue($result->saveTo($path));
            $this->assertFileExists($path);
            $this->assertSame('# Hello World', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function estimate_chunks_calculates_correctly(): void
    {
        $result = $this->makeResult(tokens: 10000);

        $this->assertSame(3, $result->estimateChunks(4096));
        $this->assertSame(10, $result->estimateChunks(1000));
        $this->assertSame(1, $result->estimateChunks(10000));
    }

    #[Test]
    public function estimate_chunks_returns_zero_for_empty(): void
    {
        $result = $this->makeResult(tokens: 0);
        $this->assertSame(0, $result->estimateChunks());
    }

    #[Test]
    public function truncate_delegates_to_token_estimator(): void
    {
        $markdown = str_repeat('a', 100);
        $result = $this->makeResult(markdown: $markdown);

        $truncated = $result->truncate(5);
        $this->assertSame(20, mb_strlen($truncated));
    }

    #[Test]
    public function is_empty_returns_true_for_blank_markdown(): void
    {
        $this->assertTrue($this->makeResult(markdown: '')->isEmpty());
        $this->assertTrue($this->makeResult(markdown: '   ')->isEmpty());
    }

    #[Test]
    public function is_empty_returns_false_for_content(): void
    {
        $this->assertFalse($this->makeResult()->isEmpty());
    }

    #[Test]
    public function to_string_returns_markdown(): void
    {
        $result = $this->makeResult();
        $this->assertSame('# Hello World', (string) $result);
    }

    #[Test]
    public function from_cache_defaults_to_false(): void
    {
        $result = $this->makeResult();
        $this->assertFalse($result->fromCache);
    }

    #[Test]
    public function from_cache_can_be_set_to_true(): void
    {
        $result = $this->makeResult(fromCache: true);
        $this->assertTrue($result->fromCache);
    }
}
