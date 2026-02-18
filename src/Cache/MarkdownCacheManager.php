<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Cache;

use Illuminate\Support\Facades\Cache;
use Moneo\MarkdownForAgents\DTOs\ConversionResult;

class MarkdownCacheManager
{
    protected bool $enabled;

    protected ?string $store;

    protected int $ttl;

    protected string $prefix;

    public function __construct(array $config = [])
    {
        $this->enabled = (bool) ($config['enabled'] ?? false);
        $this->store = $config['store'] ?? null;
        $this->ttl = (int) ($config['ttl'] ?? 3600);
        $this->prefix = $config['prefix'] ?? 'mfa_';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function get(string $key): ?ConversionResult
    {
        $data = $this->store()->get($key);

        if (! is_array($data)) {
            return null;
        }

        return new ConversionResult(
            name: $data['name'],
            markdown: $data['markdown'],
            mimeType: $data['mimeType'],
            tokens: $data['tokens'],
            contentSignals: $data['contentSignals'],
            driver: $data['driver'],
            fromCache: true,
        );
    }

    public function put(string $key, ConversionResult $result, ?int $ttl = null): void
    {
        $this->store()->put($key, $result->toArray(), $ttl ?? $this->ttl);
    }

    public function forget(string $key): bool
    {
        return $this->store()->forget($key);
    }

    public function flush(): bool
    {
        return $this->store()->clear();
    }

    public static function buildKey(string $driver, string $source, string $prefix = 'mfa_'): string
    {
        return $prefix.hash('sha256', "{$driver}:{$source}");
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    protected function store(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store($this->store);
    }
}
