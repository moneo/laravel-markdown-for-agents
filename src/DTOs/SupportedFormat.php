<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\DTOs;

final class SupportedFormat
{
    public function __construct(
        public readonly string $extension,
        public readonly string $mimeType,
    ) {}
}
