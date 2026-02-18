<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\DTOs;

use Moneo\MarkdownForAgents\Support\TokenEstimator;

final class ConversionResult
{
    public function __construct(
        public readonly string $name,
        public readonly string $markdown,
        public readonly string $mimeType,
        public readonly int $tokens,
        public readonly ?array $contentSignals,
        public readonly string $driver,
        public readonly bool $fromCache = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'markdown' => $this->markdown,
            'mimeType' => $this->mimeType,
            'tokens' => $this->tokens,
            'contentSignals' => $this->contentSignals,
            'driver' => $this->driver,
            'fromCache' => $this->fromCache,
        ];
    }

    public function toJson(int $flags = 0): string
    {
        return (string) json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    public function saveTo(string $path): bool
    {
        return file_put_contents($path, $this->markdown) !== false;
    }

    public function estimateChunks(int $chunkSize = 4096): int
    {
        if ($this->tokens === 0) {
            return 0;
        }

        return (int) ceil($this->tokens / $chunkSize);
    }

    public function truncate(int $maxTokens): string
    {
        return TokenEstimator::truncateToTokens($this->markdown, $maxTokens);
    }

    public function isEmpty(): bool
    {
        return trim($this->markdown) === '';
    }

    public function __toString(): string
    {
        return $this->markdown;
    }
}
