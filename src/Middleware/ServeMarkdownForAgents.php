<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Middleware;

use Closure;
use Illuminate\Http\Request;
use League\HTMLToMarkdown\HtmlConverter;
use Moneo\MarkdownForAgents\Support\ContentSignalParser;
use Moneo\MarkdownForAgents\Support\TokenEstimator;
use Symfony\Component\HttpFoundation\Response;

class ServeMarkdownForAgents
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $accept = $request->header('Accept', '');

        if (! is_string($accept) || ! str_contains($accept, 'text/markdown')) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $html = $response->getContent();

        if ($html === false || $html === '') {
            return $response;
        }

        $converter = new HtmlConverter;
        $markdown = $converter->convert($html);
        $tokens = TokenEstimator::estimate($markdown);

        /** @var array<string, string> $signals */
        $signals = config('markdown-for-agents.middleware.content_signals', []);
        $signalHeader = ContentSignalParser::build($signals);

        return response($markdown, $response->getStatusCode(), [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Vary' => 'accept',
            'x-markdown-tokens' => (string) $tokens,
            'Content-Signal' => $signalHeader,
        ]);
    }
}
