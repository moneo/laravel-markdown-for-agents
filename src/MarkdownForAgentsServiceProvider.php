<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents;

use Illuminate\Support\ServiceProvider;
use Moneo\MarkdownForAgents\Cache\MarkdownCacheManager;
use Moneo\MarkdownForAgents\Commands\CacheClearCommand;
use Moneo\MarkdownForAgents\Commands\ConvertCommand;
use Moneo\MarkdownForAgents\Commands\FormatsCommand;
use Moneo\MarkdownForAgents\Contracts\MarkdownConverterInterface;
use Moneo\MarkdownForAgents\Middleware\ServeMarkdownForAgents;

class MarkdownForAgentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/markdown-for-agents.php', 'markdown-for-agents');

        $this->app->singleton(MarkdownCacheManager::class, function ($app) {
            return new MarkdownCacheManager($app['config']['markdown-for-agents.cache'] ?? []);
        });

        $this->app->singleton(MarkdownForAgentsManager::class);

        $this->app->bind(MarkdownConverterInterface::class, function ($app) {
            return $app->make(MarkdownForAgentsManager::class)->resolveDefaultDriver();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/markdown-for-agents.php' => config_path('markdown-for-agents.php'),
        ], 'markdown-for-agents-config');

        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('markdown-for-agents', ServeMarkdownForAgents::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConvertCommand::class,
                FormatsCommand::class,
                CacheClearCommand::class,
            ]);
        }
    }
}
