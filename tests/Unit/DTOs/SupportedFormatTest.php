<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit\DTOs;

use Moneo\MarkdownForAgents\DTOs\SupportedFormat;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SupportedFormatTest extends TestCase
{
    #[Test]
    public function it_stores_extension_and_mime_type(): void
    {
        $format = new SupportedFormat('.pdf', 'application/pdf');

        $this->assertSame('.pdf', $format->extension);
        $this->assertSame('application/pdf', $format->mimeType);
    }

    #[Test]
    public function properties_are_readonly(): void
    {
        $format = new SupportedFormat('.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->assertSame('.docx', $format->extension);
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $format->mimeType);
    }
}
