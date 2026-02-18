<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Commands;

use Illuminate\Console\Command;
use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use Moneo\MarkdownForAgents\MarkdownForAgentsManager;

class ConvertCommand extends Command
{
    protected $signature = 'markdown:convert {source} {--driver=} {--save=}';

    protected $description = 'Convert a URL or file to Markdown';

    public function handle(MarkdownForAgentsManager $manager): int
    {
        /** @var string $source */
        $source = $this->argument('source');

        /** @var string|null $driverOption */
        $driverOption = $this->option('driver');

        /** @var string|null $savePath */
        $savePath = $this->option('save');

        if ($driverOption !== null) {
            $manager->driver($driverOption);
        }

        $isUrl = str_starts_with($source, 'http://') || str_starts_with($source, 'https://');

        $pending = $isUrl
            ? $manager->url($source)
            : $manager->driver($driverOption ?? 'workers_ai')->file($source);

        /** @var ConversionResult $result */
        $result = $pending->noCache()->convert();

        $this->info("Driver:    {$result->driver}");
        $this->info("Tokens:    {$result->tokens}");
        $this->info("MIME Type: {$result->mimeType}");
        $this->newLine();

        $preview = mb_strlen($result->markdown) > 500
            ? mb_substr($result->markdown, 0, 500).'...'
            : $result->markdown;

        $this->line($preview);

        if ($savePath !== null) {
            $result->saveTo($savePath);
            $this->newLine();
            $this->info("Saved to: {$savePath}");
        }

        return self::SUCCESS;
    }
}
