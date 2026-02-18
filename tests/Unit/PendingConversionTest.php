<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Moneo\MarkdownForAgents\Contracts\MarkdownConverterInterface;
use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use Moneo\MarkdownForAgents\Events\ConversionFailed;
use Moneo\MarkdownForAgents\Events\MarkdownConverted;
use Moneo\MarkdownForAgents\Exceptions\ConversionFailedException;
use Moneo\MarkdownForAgents\MarkdownForAgentsManager;
use Moneo\MarkdownForAgents\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PendingConversionTest extends TestCase
{
    private function mockDriver(string $name, ?ConversionResult $result = null, ?\Throwable $exception = null): MarkdownConverterInterface
    {
        $mock = $this->createMock(MarkdownConverterInterface::class);

        if ($exception !== null) {
            $mock->method('convertUrl')->willThrowException($exception);
            $mock->method('convertHtml')->willThrowException($exception);
        } elseif ($result !== null) {
            $mock->method('convertUrl')->willReturn($result);
            $mock->method('convertHtml')->willReturn($result);
            $mock->method('convertFile')->willReturn($result);
            $mock->method('convertFiles')->willReturn([$result]);
        }

        return $mock;
    }

    private function makeResult(string $driver = 'agents', string $markdown = '# Hello'): ConversionResult
    {
        return new ConversionResult('https://example.com', $markdown, 'text/markdown', 10, null, $driver);
    }

    #[Test]
    public function fluent_methods_return_self(): void
    {
        $manager = $this->app->make(MarkdownForAgentsManager::class);
        $pending = $manager->url('https://example.com');

        $this->assertSame($pending, $pending->withFallback('browser'));
        $this->assertSame($pending, $pending->noCache());
        $this->assertSame($pending, $pending->cache(3600));
        $this->assertSame($pending, $pending->waitUntil('networkidle0'));
        $this->assertSame($pending, $pending->userAgent('Bot/1.0'));
        $this->assertSame($pending, $pending->rejectPatterns(['/\\.css$/']));
        $this->assertSame($pending, $pending->cookies([]));
        $this->assertSame($pending, $pending->authenticate('user', 'pass'));
    }

    #[Test]
    public function convert_calls_convert_url_for_url_type(): void
    {
        Event::fake();

        $result = $this->makeResult();
        $mock = $this->mockDriver('agents', $result);

        $manager = $this->app->make(MarkdownForAgentsManager::class);
        $manager->extend('agents', fn () => $mock);

        $output = $manager->url('https://example.com')->noCache()->convert();

        $this->assertSame('# Hello', $output->markdown);
        Event::assertDispatched(MarkdownConverted::class);
    }

    #[Test]
    public function convert_calls_convert_html_for_html_type(): void
    {
        Event::fake();

        $result = $this->makeResult();
        $mock = $this->mockDriver('agents', $result);

        $manager = $this->app->make(MarkdownForAgentsManager::class);
        $manager->extend('agents', fn () => $mock);

        $output = $manager->html('<p>Hi</p>')->noCache()->convert();

        $this->assertSame('# Hello', $output->markdown);
    }

    #[Test]
    public function fallback_driver_is_used_on_primary_failure(): void
    {
        Event::fake();

        $failDriver = $this->mockDriver(
            'agents',
            exception: ConversionFailedException::forDriver('agents', 'https://example.com', 'Site not CF-enabled'),
        );
        $successResult = $this->makeResult('browser', '# Fallback');
        $successDriver = $this->mockDriver('browser', $successResult);

        $manager = $this->app->make(MarkdownForAgentsManager::class);
        $manager->extend('agents', fn () => $failDriver);
        $manager->extend('browser', fn () => $successDriver);

        $output = $manager->url('https://example.com')
            ->withFallback('browser')
            ->noCache()
            ->convert();

        $this->assertSame('# Fallback', $output->markdown);
        $this->assertSame('browser', $output->driver);
        Event::assertDispatched(MarkdownConverted::class);
    }

    #[Test]
    public function it_dispatches_conversion_failed_event(): void
    {
        Event::fake();

        $failDriver = $this->mockDriver(
            'agents',
            exception: ConversionFailedException::forDriver('agents', 'https://example.com', 'Failed'),
        );

        $manager = $this->app->make(MarkdownForAgentsManager::class);
        $manager->extend('agents', fn () => $failDriver);

        try {
            $manager->url('https://example.com')->noCache()->convert();
        } catch (ConversionFailedException) {
        }

        Event::assertDispatched(ConversionFailed::class, function (ConversionFailed $event) {
            return $event->source === 'https://example.com' && $event->driver === 'agents';
        });
    }

    #[Test]
    public function convert_returns_array_for_files_type(): void
    {
        Event::fake();

        $result = $this->makeResult('workers_ai');
        $mock = $this->mockDriver('workers_ai', $result);

        $manager = $this->app->make(MarkdownForAgentsManager::class);
        $manager->extend('workers_ai', fn () => $mock);

        $output = $manager->driver('workers_ai')->files(['/tmp/doc.pdf'])->noCache()->convert();

        $this->assertIsArray($output);
        $this->assertCount(1, $output);
    }
}
