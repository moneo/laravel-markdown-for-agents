<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit\Cache;

use Moneo\MarkdownForAgents\Cache\MarkdownCacheManager;
use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use Moneo\MarkdownForAgents\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MarkdownCacheManagerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('markdown-for-agents.cache.enabled', true);
        $app['config']->set('markdown-for-agents.cache.store', 'array');
        $app['config']->set('markdown-for-agents.cache.ttl', 3600);
        $app['config']->set('markdown-for-agents.cache.prefix', 'mfa_');
    }

    private function makeResult(): ConversionResult
    {
        return new ConversionResult(
            name: 'https://example.com',
            markdown: '# Hello',
            mimeType: 'text/markdown',
            tokens: 10,
            contentSignals: ['ai-train' => 'yes'],
            driver: 'agents',
        );
    }

    #[Test]
    public function it_returns_null_on_cache_miss(): void
    {
        $cache = $this->app->make(MarkdownCacheManager::class);

        $this->assertNull($cache->get('nonexistent'));
    }

    #[Test]
    public function it_stores_and_retrieves_a_result(): void
    {
        $cache = $this->app->make(MarkdownCacheManager::class);
        $result = $this->makeResult();

        $key = MarkdownCacheManager::buildKey('agents', 'https://example.com', 'mfa_');
        $cache->put($key, $result);

        $cached = $cache->get($key);

        $this->assertNotNull($cached);
        $this->assertTrue($cached->fromCache);
        $this->assertSame('# Hello', $cached->markdown);
        $this->assertSame(10, $cached->tokens);
        $this->assertSame('agents', $cached->driver);
        $this->assertSame(['ai-train' => 'yes'], $cached->contentSignals);
    }

    #[Test]
    public function it_forgets_a_cached_entry(): void
    {
        $cache = $this->app->make(MarkdownCacheManager::class);
        $result = $this->makeResult();

        $key = MarkdownCacheManager::buildKey('agents', 'https://example.com', 'mfa_');
        $cache->put($key, $result);

        $this->assertTrue($cache->forget($key));
        $this->assertNull($cache->get($key));
    }

    #[Test]
    public function it_flushes_all_entries(): void
    {
        $cache = $this->app->make(MarkdownCacheManager::class);
        $result = $this->makeResult();

        $key1 = MarkdownCacheManager::buildKey('agents', 'https://a.com', 'mfa_');
        $key2 = MarkdownCacheManager::buildKey('agents', 'https://b.com', 'mfa_');
        $cache->put($key1, $result);
        $cache->put($key2, $result);

        $this->assertTrue($cache->flush());
        $this->assertNull($cache->get($key1));
        $this->assertNull($cache->get($key2));
    }

    #[Test]
    public function build_key_produces_deterministic_hash(): void
    {
        $key1 = MarkdownCacheManager::buildKey('agents', 'https://example.com', 'mfa_');
        $key2 = MarkdownCacheManager::buildKey('agents', 'https://example.com', 'mfa_');

        $this->assertSame($key1, $key2);
        $this->assertStringStartsWith('mfa_', $key1);
    }

    #[Test]
    public function build_key_differs_for_different_drivers(): void
    {
        $key1 = MarkdownCacheManager::buildKey('agents', 'https://example.com', 'mfa_');
        $key2 = MarkdownCacheManager::buildKey('browser', 'https://example.com', 'mfa_');

        $this->assertNotSame($key1, $key2);
    }

    #[Test]
    public function is_enabled_reads_config(): void
    {
        $cache = $this->app->make(MarkdownCacheManager::class);

        $this->assertTrue($cache->isEnabled());
    }
}
