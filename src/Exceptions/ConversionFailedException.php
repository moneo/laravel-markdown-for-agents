<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Exceptions;

class ConversionFailedException extends MarkdownForAgentsException
{
    public static function forDriver(string $driver, string $source, string $message, ?\Throwable $previous = null): self
    {
        return new self(
            message: $message,
            driver: $driver,
            source: $source,
            previous: $previous,
        );
    }

    public static function unexpectedContentType(string $driver, string $source, string $expected, string $actual): self
    {
        return new self(
            message: "Expected content type [{$expected}] but received [{$actual}].",
            driver: $driver,
            source: $source,
        );
    }
}
