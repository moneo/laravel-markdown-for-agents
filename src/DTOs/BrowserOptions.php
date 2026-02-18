<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\DTOs;

final class BrowserOptions
{
    /**
     * @param  string[]  $rejectPatterns
     * @param  array<array<string, string>>  $cookies
     */
    public function __construct(
        public readonly ?string $waitUntil = null,
        public readonly ?string $userAgent = null,
        public readonly array $rejectPatterns = [],
        public readonly array $cookies = [],
        public readonly ?string $authUsername = null,
        public readonly ?string $authPassword = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = [];

        if ($this->waitUntil !== null) {
            $payload['gotoOptions'] = ['waitUntil' => $this->waitUntil];
        }

        if ($this->userAgent !== null) {
            $payload['userAgent'] = $this->userAgent;
        }

        if ($this->rejectPatterns !== []) {
            $payload['rejectRequestPattern'] = $this->rejectPatterns;
        }

        if ($this->cookies !== []) {
            $payload['cookies'] = $this->cookies;
        }

        if ($this->authUsername !== null && $this->authPassword !== null) {
            $payload['authenticate'] = [
                'username' => $this->authUsername,
                'password' => $this->authPassword,
            ];
        }

        return $payload;
    }
}
