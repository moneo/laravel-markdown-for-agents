<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Drivers;

use GuzzleHttp\HandlerStack;
use Illuminate\Http\UploadedFile;
use Moneo\MarkdownForAgents\Contracts\MarkdownConverterInterface;
use Moneo\MarkdownForAgents\DTOs\BrowserOptions;
use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use Moneo\MarkdownForAgents\DTOs\SupportedFormat;
use Moneo\MarkdownForAgents\Exceptions\AuthenticationException;
use Moneo\MarkdownForAgents\Exceptions\ConversionFailedException;
use Moneo\MarkdownForAgents\Exceptions\UnsupportedFormatException;
use Moneo\MarkdownForAgents\Http\CloudflareHttpClient;
use Moneo\MarkdownForAgents\Support\TokenEstimator;

class BrowserRenderingDriver implements MarkdownConverterInterface
{
    protected CloudflareHttpClient $http;

    protected string $baseUrl;

    public function __construct(
        protected array $config,
        ?HandlerStack $handler = null,
    ) {
        $accountId = $this->config['account_id'] ?? '';
        $apiToken = $this->config['api_token'] ?? '';

        if ($apiToken === '') {
            throw AuthenticationException::missingCredentials('browser', '');
        }

        $this->baseUrl = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/browser-rendering/markdown";

        $this->http = new CloudflareHttpClient(
            config: $this->config['drivers']['browser'] ?? [],
            apiToken: $apiToken,
            handler: $handler,
        );
    }

    public function convertUrl(string $url, array $options = []): ConversionResult
    {
        $payload = array_merge(['url' => $url], $this->buildBrowserOptions($options)->toArray());

        return $this->postAndParse($payload, $url);
    }

    public function convertHtml(string $html, array $options = []): ConversionResult
    {
        $payload = array_merge(['html' => $html], $this->buildBrowserOptions($options)->toArray());

        return $this->postAndParse($payload, 'html');
    }

    public function convertFile(string|UploadedFile $file, array $options = []): ConversionResult
    {
        $source = is_string($file) ? $file : $file->getClientOriginalName();

        throw UnsupportedFormatException::forDriver('browser', $source, 'The browser driver does not support file conversion.');
    }

    public function convertFiles(array $files, array $options = []): array
    {
        throw UnsupportedFormatException::forDriver('browser', 'files', 'The browser driver does not support file conversion.');
    }

    /** @return SupportedFormat[] */
    public function supportedFormats(): array
    {
        return [];
    }

    protected function postAndParse(array $payload, string $source): ConversionResult
    {
        $response = $this->http->postJson($this->baseUrl, $payload);
        $data = json_decode((string) $response->getBody(), true);

        if (($data['success'] ?? false) === false) {
            throw ConversionFailedException::forDriver(
                'browser',
                $source,
                $data['errors'][0]['message'] ?? 'Browser rendering failed.',
            );
        }

        $markdown = $data['result'] ?? '';

        return new ConversionResult(
            name: $source,
            markdown: $markdown,
            mimeType: 'text/markdown',
            tokens: TokenEstimator::estimate($markdown),
            contentSignals: null,
            driver: 'browser',
        );
    }

    protected function buildBrowserOptions(array $options): BrowserOptions
    {
        $driverConfig = $this->config['drivers']['browser'] ?? [];

        return new BrowserOptions(
            waitUntil: $options['waitUntil'] ?? $driverConfig['wait_until'] ?? null,
            userAgent: $options['userAgent'] ?? $driverConfig['user_agent'] ?? null,
            rejectPatterns: $options['rejectPatterns'] ?? $driverConfig['reject_patterns'] ?? [],
            cookies: $options['cookies'] ?? [],
            authUsername: $options['authUsername'] ?? null,
            authPassword: $options['authPassword'] ?? null,
        );
    }
}
