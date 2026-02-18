<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Events;

final class ConversionFailed
{
    public function __construct(
        public readonly string $source,
        public readonly string $driver,
        public readonly \Throwable $exception,
    ) {}
}
