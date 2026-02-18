<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Facades;

use Illuminate\Support\Facades\Facade;
use Moneo\MarkdownForAgents\MarkdownForAgentsManager;
use Moneo\MarkdownForAgents\PendingConversion;

/**
 * @method static PendingConversion url(string $url)
 * @method static PendingConversion html(string $html)
 * @method static PendingConversion file(string|\Illuminate\Http\UploadedFile $file)
 * @method static PendingConversion files(array $files)
 * @method static MarkdownForAgentsManager driver(string $name)
 * @method static array supportedFormats()
 * @method static bool clearCache(?string $key = null)
 * @method static bool flushCache()
 * @method static void extend(string $name, \Closure $resolver)
 *
 * @see \Moneo\MarkdownForAgents\MarkdownForAgentsManager
 */
class MarkdownForAgents extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MarkdownForAgentsManager::class;
    }
}
