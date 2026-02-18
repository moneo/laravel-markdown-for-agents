<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit\Support;

use Moneo\MarkdownForAgents\Support\TokenEstimator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TokenEstimatorTest extends TestCase
{
    #[Test]
    public function it_estimates_zero_for_empty_string(): void
    {
        $this->assertSame(0, TokenEstimator::estimate(''));
    }

    #[Test]
    public function it_estimates_tokens_using_char_divided_by_four(): void
    {
        $this->assertSame(1, TokenEstimator::estimate('abc'));
        $this->assertSame(1, TokenEstimator::estimate('abcd'));
        $this->assertSame(2, TokenEstimator::estimate('abcde'));
        $this->assertSame(3, TokenEstimator::estimate('hello world!'));
    }

    #[Test]
    public function it_handles_multibyte_characters(): void
    {
        $text = str_repeat('ü', 8);
        $this->assertSame(2, TokenEstimator::estimate($text));
    }

    #[Test]
    public function it_truncates_to_zero_tokens(): void
    {
        $this->assertSame('', TokenEstimator::truncateToTokens('hello world', 0));
    }

    #[Test]
    public function it_truncates_to_negative_tokens(): void
    {
        $this->assertSame('', TokenEstimator::truncateToTokens('hello world', -5));
    }

    #[Test]
    public function it_returns_full_text_when_within_limit(): void
    {
        $text = 'hi';
        $this->assertSame($text, TokenEstimator::truncateToTokens($text, 100));
    }

    #[Test]
    public function it_truncates_text_exceeding_max_tokens(): void
    {
        $text = str_repeat('a', 20);
        $truncated = TokenEstimator::truncateToTokens($text, 2);

        $this->assertSame(8, mb_strlen($truncated));
        $this->assertSame(str_repeat('a', 8), $truncated);
    }
}
