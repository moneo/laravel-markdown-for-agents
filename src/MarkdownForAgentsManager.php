<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Moneo\MarkdownForAgents\Cache\MarkdownCacheManager;
use Moneo\MarkdownForAgents\Contracts\MarkdownConverterInterface;

class MarkdownForAgentsManager
{
    protected ?string $driver = null;

    /** @var array<string, Closure> */
    protected array $customCreators = [];

    /** @var array<string, MarkdownConverterInterface> */
    protected array $resolvedDrivers = [];

    public function __construct(
        protected Application $app,
    ) {}

    public function driver(string $name): self
    {
        $this->driver = $name;

        return $this;
    }

    public function url(string $url): PendingConversion
    {
        return $this->newPendingConversion('url', $url);
    }

    public function html(string $html): PendingConversion
    {
        return $this->newPendingConversion('html', $html);
    }

    public function file(string|\Illuminate\Http\UploadedFile $file): PendingConversion
    {
        return $this->newPendingConversion('file', $file);
    }

    /** @param array<string|\Illuminate\Http\UploadedFile> $files */
    public function files(array $files): PendingConversion
    {
        return $this->newPendingConversion('files', $files);
    }

    /** @return array<\Moneo\MarkdownForAgents\DTOs\SupportedFormat> */
    public function supportedFormats(): array
    {
        return $this->resolve('workers_ai')->supportedFormats();
    }

    public function clearCache(?string $key = null): bool
    {
        $cache = $this->cacheManager();

        if ($key !== null) {
            $cacheKey = MarkdownCacheManager::buildKey(
                $this->getDefaultDriver(),
                $key,
                $cache->getPrefix(),
            );

            return $cache->forget($cacheKey);
        }

        return $cache->flush();
    }

    public function flushCache(): bool
    {
        return $this->cacheManager()->flush();
    }

    public function extend(string $name, Closure $resolver): void
    {
        $this->customCreators[$name] = $resolver;
    }

    public function resolve(?string $name = null): MarkdownConverterInterface
    {
        $name ??= $this->getDriverName();

        return $this->resolvedDrivers[$name] ??= $this->createDriver($name);
    }

    public function resolveDefaultDriver(): MarkdownConverterInterface
    {
        return $this->resolve($this->getDefaultDriver());
    }

    public function getDriverName(): string
    {
        $name = $this->driver ?? $this->getDefaultDriver();
        $this->driver = null;

        return $name;
    }

    public function getDefaultDriver(): string
    {
        /** @var string $default */
        $default = $this->app->make('config')->get('markdown-for-agents.default', 'agents');

        return $default;
    }

    public function cacheManager(): MarkdownCacheManager
    {
        return $this->app->make(MarkdownCacheManager::class);
    }

    public function getApp(): Application
    {
        return $this->app;
    }

    protected function newPendingConversion(string $type, mixed $source): PendingConversion
    {
        return new PendingConversion($this, $type, $source);
    }

    protected function createDriver(string $name): MarkdownConverterInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->app);
        }

        return match ($name) {
            'agents' => $this->createAgentsDriver(),
            'workers_ai' => $this->createWorkersAiDriver(),
            'browser' => $this->createBrowserDriver(),
            default => throw new \InvalidArgumentException("Driver [{$name}] is not supported."),
        };
    }

    /** @return array<string, mixed> */
    protected function getConfig(): array
    {
        /** @var array<string, mixed> $config */
        $config = $this->app->make('config')->get('markdown-for-agents', []);

        return $config;
    }

    protected function createAgentsDriver(): MarkdownConverterInterface
    {
        return new Drivers\MarkdownForAgentsDriver($this->getConfig());
    }

    protected function createWorkersAiDriver(): MarkdownConverterInterface
    {
        return new Drivers\WorkersAiDriver($this->getConfig());
    }

    protected function createBrowserDriver(): MarkdownConverterInterface
    {
        return new Drivers\BrowserRenderingDriver($this->getConfig());
    }
}
