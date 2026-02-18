<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Exceptions;

class RateLimitException extends MarkdownForAgentsException
{
    public readonly ?int $retryAfter;

    public function __construct(
        string $message,
        string $driver = '',
        string $source = '',
        ?int $retryAfter = null,
        int $code = 429,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $driver, $source, $code, $previous);
        $this->retryAfter = $retryAfter;
    }

    public static function fromResponse(string $driver, string $source, ?int $retryAfter = null, ?\Throwable $previous = null): self
    {
        $message = 'Rate limit exceeded.';
        if ($retryAfter !== null) {
            $message .= " Retry after {$retryAfter} seconds.";
        }

        return new self(
            message: $message,
            driver: $driver,
            source: $source,
            retryAfter: $retryAfter,
            previous: $previous,
        );
    }
}
