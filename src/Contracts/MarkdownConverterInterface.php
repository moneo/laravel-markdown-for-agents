<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Contracts;

use Moneo\MarkdownForAgents\DTOs\ConversionResult;
use Moneo\MarkdownForAgents\DTOs\SupportedFormat;

interface MarkdownConverterInterface
{
    public function convertUrl(string $url, array $options = []): ConversionResult;

    public function convertHtml(string $html, array $options = []): ConversionResult;

    public function convertFile(string|\Illuminate\Http\UploadedFile $file, array $options = []): ConversionResult;

    /**
     * @param  array<string|\Illuminate\Http\UploadedFile>  $files
     * @return ConversionResult[]
     */
    public function convertFiles(array $files, array $options = []): array;

    /** @return SupportedFormat[] */
    public function supportedFormats(): array;
}
