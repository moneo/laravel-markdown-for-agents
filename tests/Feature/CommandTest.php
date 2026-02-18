<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Feature;

use Moneo\MarkdownForAgents\Contracts\MarkdownConverterInterface;
use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use Moneo\MarkdownForAgents\DTOs\SupportedFormat;
use Moneo\MarkdownForAgents\MarkdownForAgentsManager;
use Moneo\MarkdownForAgents\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CommandTest extends TestCase
{
    private function registerMockDriver(string $name, ?ConversionResult $result = null, array $formats = []): void
    {
        $mock = $this->createMock(MarkdownConverterInterface::class);

        if ($result !== null) {
            $mock->method('convertUrl')->willReturn($result);
            $mock->method('convertFile')->willReturn($result);
        }

        $mock->method('supportedFormats')->willReturn($formats);

        $manager = $this->app->make(MarkdownForAgentsManager::class);
        $manager->extend($name, fn () => $mock);
    }

    #[Test]
    public function convert_command_converts_url(): void
    {
        $this->registerMockDriver('agents', new ConversionResult(
            'https://example.com', '# Hello', 'text/markdown', 50, null, 'agents',
        ));

        $this->artisan('markdown:convert', ['source' => 'https://example.com', '--driver' => 'agents'])
            ->assertSuccessful()
            ->expectsOutputToContain('Driver:    agents')
            ->expectsOutputToContain('Tokens:    50')
            ->expectsOutputToContain('# Hello');
    }

    #[Test]
    public function convert_command_saves_to_file(): void
    {
        $this->registerMockDriver('agents', new ConversionResult(
            'https://example.com', '# Saved', 'text/markdown', 10, null, 'agents',
        ));

        $path = sys_get_temp_dir().'/mfa_cmd_test_'.uniqid().'.md';

        try {
            $this->artisan('markdown:convert', [
                'source' => 'https://example.com',
                '--driver' => 'agents',
                '--save' => $path,
            ])->assertSuccessful()->expectsOutputToContain("Saved to: {$path}");

            $this->assertFileExists($path);
            $this->assertSame('# Saved', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function formats_command_displays_table(): void
    {
        $this->registerMockDriver('workers_ai', formats: [
            new SupportedFormat('.pdf', 'application/pdf'),
            new SupportedFormat('.png', 'image/png'),
        ]);

        $this->artisan('markdown:formats')
            ->assertSuccessful()
            ->expectsOutputToContain('.pdf')
            ->expectsOutputToContain('.png');
    }

    #[Test]
    public function cache_clear_command_flushes_all(): void
    {
        $this->artisan('markdown:cache:clear')
            ->assertSuccessful()
            ->expectsOutputToContain('flushed');
    }

    #[Test]
    public function cache_clear_command_clears_specific_url(): void
    {
        $this->artisan('markdown:cache:clear', ['--url' => 'https://example.com'])
            ->assertSuccessful()
            ->expectsOutputToContain('Cache cleared for: https://example.com');
    }
}
