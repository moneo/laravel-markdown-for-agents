<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Tests\Unit\DTOs;

use Moneo\MarkdownForAgents\DTOs\BrowserOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BrowserOptionsTest extends TestCase
{
    #[Test]
    public function to_array_returns_empty_for_defaults(): void
    {
        $options = new BrowserOptions;
        $this->assertSame([], $options->toArray());
    }

    #[Test]
    public function to_array_includes_wait_until(): void
    {
        $options = new BrowserOptions(waitUntil: 'networkidle0');
        $array = $options->toArray();

        $this->assertSame(['waitUntil' => 'networkidle0'], $array['gotoOptions']);
    }

    #[Test]
    public function to_array_includes_user_agent(): void
    {
        $options = new BrowserOptions(userAgent: 'MyBot/1.0');
        $array = $options->toArray();

        $this->assertSame('MyBot/1.0', $array['userAgent']);
    }

    #[Test]
    public function to_array_includes_reject_patterns(): void
    {
        $options = new BrowserOptions(rejectPatterns: ['/^.*\.(css)/']);
        $array = $options->toArray();

        $this->assertSame(['/^.*\.(css)/'], $array['rejectRequestPattern']);
    }

    #[Test]
    public function to_array_includes_cookies(): void
    {
        $cookies = [['name' => 'session', 'value' => 'abc123', 'domain' => '.example.com']];
        $options = new BrowserOptions(cookies: $cookies);
        $array = $options->toArray();

        $this->assertSame($cookies, $array['cookies']);
    }

    #[Test]
    public function to_array_includes_authenticate(): void
    {
        $options = new BrowserOptions(authUsername: 'user', authPassword: 'pass');
        $array = $options->toArray();

        $this->assertSame(['username' => 'user', 'password' => 'pass'], $array['authenticate']);
    }

    #[Test]
    public function to_array_omits_partial_authenticate(): void
    {
        $options = new BrowserOptions(authUsername: 'user');
        $array = $options->toArray();

        $this->assertArrayNotHasKey('authenticate', $array);
    }

    #[Test]
    public function to_array_includes_all_options_together(): void
    {
        $options = new BrowserOptions(
            waitUntil: 'load',
            userAgent: 'Bot/2.0',
            rejectPatterns: ['/\.css$/'],
            cookies: [['name' => 'tok', 'value' => '123', 'domain' => '.test.com']],
            authUsername: 'admin',
            authPassword: 'secret',
        );

        $array = $options->toArray();

        $this->assertArrayHasKey('gotoOptions', $array);
        $this->assertArrayHasKey('userAgent', $array);
        $this->assertArrayHasKey('rejectRequestPattern', $array);
        $this->assertArrayHasKey('cookies', $array);
        $this->assertArrayHasKey('authenticate', $array);
    }
}
