<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Support;

final class TokenEstimator
{
    public static function estimate(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return (int) ceil(mb_strlen($text) / 4);
    }

    public static function truncateToTokens(string $text, int $maxTokens): string
    {
        if ($maxTokens <= 0) {
            return '';
        }

        $maxChars = $maxTokens * 4;

        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars);
    }
}
