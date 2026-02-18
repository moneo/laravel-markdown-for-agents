<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents;

use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Tappable;
use Moneo\MarkdownForAgents\Cache\MarkdownCacheManager;
use Moneo\MarkdownForAgents\Contracts\MarkdownConverterInterface;
use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use Moneo\MarkdownForAgents\Events\ConversionFailed;
use Moneo\MarkdownForAgents\Events\MarkdownConverted;
use Moneo\MarkdownForAgents\Exceptions\MarkdownForAgentsException;

class PendingConversion
{
    use Conditionable;
    use Tappable;

    protected ?string $fallbackDriver = null;

    protected ?bool $cacheEnabled = null;

    protected ?int $cacheTtl = null;

    protected ?string $waitUntilOption = null;

    protected ?string $userAgentOption = null;

    /** @var string[] */
    protected array $rejectPatternsOption = [];

    /** @var array<array<string, string>> */
    protected array $cookiesOption = [];

    protected ?string $authUsername = null;

    protected ?string $authPassword = null;

    public function __construct(
        protected MarkdownForAgentsManager $manager,
        protected string $type,
        protected mixed $source,
    ) {}

    public function withFallback(string $driver): self
    {
        $this->fallbackDriver = $driver;

        return $this;
    }

    public function noCache(): self
    {
        $this->cacheEnabled = false;

        return $this;
    }

    public function cache(int $ttl): self
    {
        $this->cacheEnabled = true;
        $this->cacheTtl = $ttl;

        return $this;
    }

    public function waitUntil(string $event): self
    {
        $this->waitUntilOption = $event;

        return $this;
    }

    public function userAgent(string $ua): self
    {
        $this->userAgentOption = $ua;

        return $this;
    }

    /** @param string[] $patterns */
    public function rejectPatterns(array $patterns): self
    {
        $this->rejectPatternsOption = $patterns;

        return $this;
    }

    /** @param array<array<string, string>> $cookies */
    public function cookies(array $cookies): self
    {
        $this->cookiesOption = $cookies;

        return $this;
    }

    public function authenticate(string $user, string $pass): self
    {
        $this->authUsername = $user;
        $this->authPassword = $pass;

        return $this;
    }

    /** @return ConversionResult|ConversionResult[] */
    public function convert(): ConversionResult|array
    {
        $driverName = $this->manager->getDriverName();
        $sourceId = $this->sourceIdentifier();
        $startTime = microtime(true);

        $cacheManager = $this->manager->cacheManager();
        $shouldCache = $this->shouldCache($cacheManager) && in_array($this->type, ['url', 'html']);
        $cacheKey = '';

        if ($shouldCache) {
            $cacheKey = MarkdownCacheManager::buildKey($driverName, $sourceId, $cacheManager->getPrefix());
            $cached = $cacheManager->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $result = $this->executeConversion($this->manager->resolve($driverName), $driverName);
        } catch (MarkdownForAgentsException $e) {
            if ($this->fallbackDriver !== null) {
                try {
                    $driverName = $this->fallbackDriver;
                    $result = $this->executeConversion($this->manager->resolve($driverName), $driverName);
                } catch (MarkdownForAgentsException $fallbackException) {
                    $this->dispatchFailed($sourceId, $driverName, $fallbackException);

                    throw $fallbackException;
                }
            } else {
                $this->dispatchFailed($sourceId, $driverName, $e);

                throw $e;
            }
        }

        if ($shouldCache && $result instanceof ConversionResult && $cacheKey !== '') {
            $cacheManager->put($cacheKey, $result, $this->cacheTtl);
        }

        $duration = microtime(true) - $startTime;

        if ($result instanceof ConversionResult) {
            $this->dispatchConverted($result, $sourceId, $duration);
        } elseif (is_array($result) && $result !== []) {
            $this->dispatchConverted($result[0], $sourceId, $duration);
        }

        return $result;
    }

    /** @return ConversionResult|ConversionResult[] */
    protected function executeConversion(MarkdownConverterInterface $driver, string $driverName): ConversionResult|array
    {
        $options = $this->buildOptions();

        return match ($this->type) {
            'url' => $driver->convertUrl((string) $this->source, $options),
            'html' => $driver->convertHtml((string) $this->source, $options),
            'file' => $driver->convertFile($this->source, $options),
            'files' => $driver->convertFiles($this->source, $options),
            default => throw new \InvalidArgumentException("Unknown conversion type [{$this->type}]."),
        };
    }

    /** @return array<string, mixed> */
    protected function buildOptions(): array
    {
        $options = [];

        if ($this->waitUntilOption !== null) {
            $options['waitUntil'] = $this->waitUntilOption;
        }

        if ($this->userAgentOption !== null) {
            $options['userAgent'] = $this->userAgentOption;
        }

        if ($this->rejectPatternsOption !== []) {
            $options['rejectPatterns'] = $this->rejectPatternsOption;
        }

        if ($this->cookiesOption !== []) {
            $options['cookies'] = $this->cookiesOption;
        }

        if ($this->authUsername !== null && $this->authPassword !== null) {
            $options['authUsername'] = $this->authUsername;
            $options['authPassword'] = $this->authPassword;
        }

        return $options;
    }

    protected function sourceIdentifier(): string
    {
        if (is_string($this->source)) {
            return $this->source;
        }

        if ($this->source instanceof \Illuminate\Http\UploadedFile) {
            return $this->source->getClientOriginalName();
        }

        if (is_array($this->source)) {
            return 'batch['.count($this->source).']';
        }

        return 'unknown';
    }

    protected function shouldCache(MarkdownCacheManager $cacheManager): bool
    {
        if ($this->cacheEnabled === false) {
            return false;
        }

        if ($this->cacheEnabled === true) {
            return true;
        }

        return $cacheManager->isEnabled();
    }

    protected function dispatchConverted(ConversionResult $result, string $source, float $duration): void
    {
        event(new MarkdownConverted($result, $source, $duration));
    }

    protected function dispatchFailed(string $source, string $driver, \Throwable $exception): void
    {
        event(new ConversionFailed($source, $driver, $exception));
    }
}
