<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Exceptions;

class MarkdownForAgentsException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $driver = '',
        public readonly string $source = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
