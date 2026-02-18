<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Commands;

use Illuminate\Console\Command;
use Moneo\MarkdownForAgents\MarkdownForAgentsManager;

class FormatsCommand extends Command
{
    protected $signature = 'markdown:formats';

    protected $description = 'List supported file formats for Workers AI conversion';

    public function handle(MarkdownForAgentsManager $manager): int
    {
        $formats = $manager->supportedFormats();

        if ($formats === []) {
            $this->warn('No supported formats returned.');

            return self::SUCCESS;
        }

        $rows = array_map(fn ($format) => [
            $format->extension,
            $format->mimeType,
        ], $formats);

        $this->table(['Extension', 'MIME Type'], $rows);

        return self::SUCCESS;
    }
}
