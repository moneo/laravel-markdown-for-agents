<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Support;

final class ContentSignalParser
{
    /** @return array<string, string> */
    public static function parse(string $header): array
    {
        if (trim($header) === '') {
            return [];
        }

        $signals = [];
        $parts = explode(',', $header);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $segments = explode('=', $part, 2);
            if (count($segments) === 2) {
                $signals[trim($segments[0])] = trim($segments[1]);
            }
        }

        return $signals;
    }

    /** @param array<string, string> $signals */
    public static function build(array $signals): string
    {
        if ($signals === []) {
            return '';
        }

        $parts = [];
        foreach ($signals as $key => $value) {
            $parts[] = "{$key}={$value}";
        }

        return implode(', ', $parts);
    }
}
