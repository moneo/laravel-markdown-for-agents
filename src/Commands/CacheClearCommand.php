<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Commands;

use Illuminate\Console\Command;
use Moneo\MarkdownForAgents\MarkdownForAgentsManager;

class CacheClearCommand extends Command
{
    protected $signature = 'markdown:cache:clear {--url=}';

    protected $description = 'Clear the Markdown conversion cache';

    public function handle(MarkdownForAgentsManager $manager): int
    {
        /** @var string|null $url */
        $url = $this->option('url');

        if ($url !== null) {
            $manager->clearCache($url);
            $this->info("Cache cleared for: {$url}");
        } else {
            $manager->flushCache();
            $this->info('All markdown cache entries flushed.');
        }

        return self::SUCCESS;
    }
}
