<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit\Support;

use Moneo\MarkdownForAgents\Support\ContentSignalParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentSignalParserTest extends TestCase
{
    #[Test]
    public function it_parses_a_standard_content_signal_header(): void
    {
        $header = 'ai-train=yes, search=yes, ai-input=yes';
        $result = ContentSignalParser::parse($header);

        $this->assertSame([
            'ai-train' => 'yes',
            'search' => 'yes',
            'ai-input' => 'yes',
        ], $result);
    }

    #[Test]
    public function it_returns_empty_array_for_empty_string(): void
    {
        $this->assertSame([], ContentSignalParser::parse(''));
        $this->assertSame([], ContentSignalParser::parse('   '));
    }

    #[Test]
    public function it_handles_whitespace_variations(): void
    {
        $header = ' ai-train = yes ,  search=no ';
        $result = ContentSignalParser::parse($header);

        $this->assertSame([
            'ai-train' => 'yes',
            'search' => 'no',
        ], $result);
    }

    #[Test]
    public function it_skips_malformed_segments(): void
    {
        $header = 'ai-train=yes, invalid, search=no';
        $result = ContentSignalParser::parse($header);

        $this->assertSame([
            'ai-train' => 'yes',
            'search' => 'no',
        ], $result);
    }

    #[Test]
    public function it_builds_a_header_from_an_array(): void
    {
        $signals = ['ai-train' => 'yes', 'search' => 'yes'];
        $result = ContentSignalParser::build($signals);

        $this->assertSame('ai-train=yes, search=yes', $result);
    }

    #[Test]
    public function it_builds_empty_string_from_empty_array(): void
    {
        $this->assertSame('', ContentSignalParser::build([]));
    }

    #[Test]
    public function it_round_trips_parse_and_build(): void
    {
        $original = 'ai-train=yes, search=yes, ai-input=yes';
        $parsed = ContentSignalParser::parse($original);
        $rebuilt = ContentSignalParser::build($parsed);

        $this->assertSame($original, $rebuilt);
    }
}
