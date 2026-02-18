<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests;

use Moneo\MarkdownForAgents\Facades\MarkdownForAgents;
use Moneo\MarkdownForAgents\MarkdownForAgentsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [MarkdownForAgentsServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['MarkdownForAgents' => MarkdownForAgents::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('markdown-for-agents.account_id', 'test-account-id');
        $app['config']->set('markdown-for-agents.api_token', 'test-api-token');
        $app['config']->set('markdown-for-agents.cache.enabled', false);
    }
}
