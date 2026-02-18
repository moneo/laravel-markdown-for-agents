<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Drivers;

use GuzzleHttp\HandlerStack;
use Moneo\MarkdownForAgents\Contracts\MarkdownConverterInterface;
use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use Moneo\MarkdownForAgents\DTOs\SupportedFormat;
use Moneo\MarkdownForAgents\Exceptions\ConversionFailedException;
use Moneo\MarkdownForAgents\Exceptions\UnsupportedFormatException;
use Moneo\MarkdownForAgents\Http\CloudflareHttpClient;
use Moneo\MarkdownForAgents\Support\ContentSignalParser;
use Moneo\MarkdownForAgents\Support\TokenEstimator;

class MarkdownForAgentsDriver implements MarkdownConverterInterface
{
    protected CloudflareHttpClient $http;

    public function __construct(
        protected array $config,
        ?HandlerStack $handler = null,
    ) {
        $this->http = new CloudflareHttpClient(
            config: $this->config['drivers']['agents'] ?? [],
            apiToken: null,
            handler: $handler,
        );
    }

    public function convertUrl(string $url, array $options = []): ConversionResult
    {
        $response = $this->http->get($url, ['Accept' => 'text/markdown']);

        $contentType = $response->getHeaderLine('Content-Type');

        if (! str_contains($contentType, 'text/markdown')) {
            throw ConversionFailedException::unexpectedContentType('agents', $url, 'text/markdown', $contentType);
        }

        $markdown = (string) $response->getBody();

        $tokensHeader = $response->getHeaderLine('x-markdown-tokens');
        $tokens = $tokensHeader !== '' ? (int) $tokensHeader : TokenEstimator::estimate($markdown);

        $signalHeader = $response->getHeaderLine('Content-Signal');
        $contentSignals = $signalHeader !== '' ? ContentSignalParser::parse($signalHeader) : null;

        return new ConversionResult(
            name: $url,
            markdown: $markdown,
            mimeType: 'text/markdown',
            tokens: $tokens,
            contentSignals: $contentSignals,
            driver: 'agents',
        );
    }

    public function convertHtml(string $html, array $options = []): ConversionResult
    {
        throw UnsupportedFormatException::forDriver('agents', 'html', 'The agents driver does not support HTML conversion.');
    }

    public function convertFile(string|\Illuminate\Http\UploadedFile $file, array $options = []): ConversionResult
    {
        $source = is_string($file) ? $file : $file->getClientOriginalName();

        throw UnsupportedFormatException::forDriver('agents', $source, 'The agents driver does not support file conversion.');
    }

    public function convertFiles(array $files, array $options = []): array
    {
        throw UnsupportedFormatException::forDriver('agents', 'files', 'The agents driver does not support file conversion.');
    }

    /** @return SupportedFormat[] */
    public function supportedFormats(): array
    {
        return [];
    }
}
