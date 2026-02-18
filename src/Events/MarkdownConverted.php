<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Events;

use Moneo\MarkdownForAgents\DTOs\ConversionResult;

final class MarkdownConverted
{
    public function __construct(
        public readonly ConversionResult $result,
        public readonly string $source,
        public readonly float $duration,
    ) {}
}
