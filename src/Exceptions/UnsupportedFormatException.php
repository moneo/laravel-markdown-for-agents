<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Exceptions;

class UnsupportedFormatException extends MarkdownForAgentsException
{
    public static function forDriver(string $driver, string $source, string $message): self
    {
        return new self(
            message: $message,
            driver: $driver,
            source: $source,
        );
    }
}
