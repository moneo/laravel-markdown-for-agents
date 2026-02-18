<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Exceptions;

class AuthenticationException extends MarkdownForAgentsException
{
    public static function missingCredentials(string $driver, string $source): self
    {
        return new self(
            message: 'API token is missing or not configured.',
            driver: $driver,
            source: $source,
        );
    }

    public static function invalidToken(string $driver, string $source, ?\Throwable $previous = null): self
    {
        return new self(
            message: 'The API token is invalid or has been revoked.',
            driver: $driver,
            source: $source,
            code: 401,
            previous: $previous,
        );
    }
}
