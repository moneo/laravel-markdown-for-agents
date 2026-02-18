<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit;

use Moneo\MarkdownForAgents\Contracts\MarkdownConverterInterface;
use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use Moneo\MarkdownForAgents\MarkdownForAgentsManager;
use Moneo\MarkdownForAgents\PendingConversion;
use Moneo\MarkdownForAgents\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ManagerTest extends TestCase
{
    #[Test]
    public function it_uses_default_driver_from_config(): void
    {
        $manager = $this->app->make(MarkdownForAgentsManager::class);

        $this->assertSame('agents', $manager->getDefaultDriver());
    }

    #[Test]
    public function it_overrides_driver(): void
    {
        $manager = $this->app->make(MarkdownForAgentsManager::class);
        $manager->driver('browser');

        $this->assertSame('browser', $manager->getDriverName());
    }

    #[Test]
    public function it_resets_driver_after_getting_name(): void
    {
        $manager = $this->app->make(MarkdownForAgentsManager::class);
        $manager->driver('browser');
        $manager->getDriverName();

        $this->assertSame('agents', $manager->getDriverName());
    }

    #[Test]
    public function url_returns_pending_conversion(): void
    {
        $manager = $this->app->make(MarkdownForAgentsManager::class);

        $this->assertInstanceOf(PendingConversion::class, $manager->url('https://example.com'));
    }

    #[Test]
    public function html_returns_pending_conversion(): void
    {
        $manager = $this->app->make(MarkdownForAgentsManager::class);

        $this->assertInstanceOf(PendingConversion::class, $manager->html('<p>Hi</p>'));
    }

    #[Test]
    public function file_returns_pending_conversion(): void
    {
        $manager = $this->app->make(MarkdownForAgentsManager::class);

        $this->assertInstanceOf(PendingConversion::class, $manager->file('/path/to/doc.pdf'));
    }

    #[Test]
    public function files_returns_pending_conversion(): void
    {
        $manager = $this->app->make(MarkdownForAgentsManager::class);

        $this->assertInstanceOf(PendingConversion::class, $manager->files(['/path/to/doc.pdf']));
    }

    #[Test]
    public function extend_registers_custom_driver(): void
    {
        $manager = $this->app->make(MarkdownForAgentsManager::class);

        $mockDriver = $this->createMock(MarkdownConverterInterface::class);
        $mockDriver->method('convertUrl')->willReturn(
            new ConversionResult('test', '# Custom', 'text/markdown', 10, null, 'custom')
        );

        $manager->extend('custom', fn () => $mockDriver);

        $resolved = $manager->resolve('custom');
        $this->assertSame($mockDriver, $resolved);
    }

    #[Test]
    public function it_throws_for_unknown_driver(): void
    {
        $manager = $this->app->make(MarkdownForAgentsManager::class);

        $this->expectException(\InvalidArgumentException::class);

        $manager->resolve('nonexistent');
    }
}
