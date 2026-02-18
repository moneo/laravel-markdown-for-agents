<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Drivers;

use GuzzleHttp\HandlerStack;
use Illuminate\Http\UploadedFile;
use Moneo\MarkdownForAgents\Contracts\MarkdownConverterInterface;
use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use Moneo\MarkdownForAgents\DTOs\SupportedFormat;
use Moneo\MarkdownForAgents\Exceptions\AuthenticationException;
use Moneo\MarkdownForAgents\Exceptions\ConversionFailedException;
use Moneo\MarkdownForAgents\Exceptions\UnsupportedFormatException;
use Moneo\MarkdownForAgents\Http\CloudflareHttpClient;
use Moneo\MarkdownForAgents\Support\TokenEstimator;

class WorkersAiDriver implements MarkdownConverterInterface
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
            throw AuthenticationException::missingCredentials('workers_ai', '');
        }

        $this->baseUrl = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/tomarkdown";

        $this->http = new CloudflareHttpClient(
            config: $this->config['drivers']['workers_ai'] ?? [],
            apiToken: $apiToken,
            handler: $handler,
        );
    }

    public function convertUrl(string $url, array $options = []): ConversionResult
    {
        throw UnsupportedFormatException::forDriver('workers_ai', $url, 'The workers_ai driver does not support URL conversion.');
    }

    public function convertHtml(string $html, array $options = []): ConversionResult
    {
        $multipart = [
            [
                'name' => 'files',
                'contents' => $html,
                'filename' => 'input.html',
                'headers' => ['Content-Type' => 'text/html'],
            ],
        ];

        $response = $this->http->postMultipart($this->baseUrl, $multipart);
        $data = json_decode((string) $response->getBody(), true);

        return $this->parseResultItem($data[0] ?? [], 'html');
    }

    public function convertFile(string|UploadedFile $file, array $options = []): ConversionResult
    {
        $multipart = [$this->buildMultipartEntry($file)];

        $response = $this->http->postMultipart($this->baseUrl, $multipart);
        $data = json_decode((string) $response->getBody(), true);

        $source = is_string($file) ? basename($file) : $file->getClientOriginalName();

        return $this->parseResultItem($data[0] ?? [], $source);
    }

    /** @return ConversionResult[] */
    public function convertFiles(array $files, array $options = []): array
    {
        $multipart = array_map(fn ($file) => $this->buildMultipartEntry($file), $files);

        $response = $this->http->postMultipart($this->baseUrl, $multipart);
        $data = json_decode((string) $response->getBody(), true);

        $results = [];
        foreach ($data as $item) {
            $results[] = $this->parseResultItem($item, $item['name'] ?? 'unknown');
        }

        return $results;
    }

    /** @return SupportedFormat[] */
    public function supportedFormats(): array
    {
        $response = $this->http->get("{$this->baseUrl}/supported");
        $data = json_decode((string) $response->getBody(), true);

        return array_map(
            fn (array $item) => new SupportedFormat(
                extension: $item['extension'],
                mimeType: $item['mimeType'],
            ),
            $data,
        );
    }

    /** @return array{name: string, contents: resource|string, filename: string} */
    protected function buildMultipartEntry(string|UploadedFile $file): array
    {
        if ($file instanceof UploadedFile) {
            $stream = fopen($file->getRealPath(), 'r');
            if ($stream === false) {
                throw ConversionFailedException::forDriver('workers_ai', $file->getClientOriginalName(), 'Unable to open file for reading.');
            }

            return [
                'name' => 'files',
                'contents' => $stream,
                'filename' => $file->getClientOriginalName(),
            ];
        }

        $stream = fopen($file, 'r');
        if ($stream === false) {
            throw ConversionFailedException::forDriver('workers_ai', $file, 'Unable to open file for reading.');
        }

        return [
            'name' => 'files',
            'contents' => $stream,
            'filename' => basename($file),
        ];
    }

    protected function parseResultItem(array $item, string $source): ConversionResult
    {
        if (($item['format'] ?? '') === 'error') {
            throw ConversionFailedException::forDriver(
                'workers_ai',
                $source,
                $item['error'] ?? 'Failed to convert document',
            );
        }

        return new ConversionResult(
            name: $item['name'] ?? $source,
            markdown: $item['data'] ?? '',
            mimeType: $item['mimeType'] ?? 'application/octet-stream',
            tokens: $item['tokens'] ?? TokenEstimator::estimate($item['data'] ?? ''),
            contentSignals: null,
            driver: 'workers_ai',
        );
    }
}
