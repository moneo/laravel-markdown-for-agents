<?php

declare(strict_types=1);

namespace Moneo\MarkdownForAgents\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Moneo\MarkdownForAgents\Exceptions\AuthenticationException;
use Moneo\MarkdownForAgents\Exceptions\MarkdownForAgentsException;
use Moneo\MarkdownForAgents\Exceptions\RateLimitException;
use Psr\Http\Message\ResponseInterface;

class CloudflareHttpClient
{
    protected Client $client;

    protected int $maxRetries;

    protected int $retryDelay;

    /**
     * @param  array{timeout?: int, retry?: int, retry_delay?: int}  $config
     */
    public function __construct(
        protected array $config = [],
        protected ?string $apiToken = null,
        ?HandlerStack $handler = null,
    ) {
        $this->maxRetries = $config['retry'] ?? 3;
        $this->retryDelay = $config['retry_delay'] ?? 100;

        $stack = $handler ?? HandlerStack::create();
        $stack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));

        $clientConfig = [
            'handler' => $stack,
            'timeout' => $config['timeout'] ?? 30,
            'http_errors' => false,
        ];

        $this->client = new Client($clientConfig);
    }

    public function get(string $url, array $headers = []): ResponseInterface
    {
        return $this->request('GET', $url, ['headers' => $this->mergeHeaders($headers)]);
    }

    public function post(string $url, array $data, array $headers = []): ResponseInterface
    {
        return $this->request('POST', $url, [
            'headers' => $this->mergeHeaders($headers),
            'form_params' => $data,
        ]);
    }

    public function postJson(string $url, array $json, array $headers = []): ResponseInterface
    {
        return $this->request('POST', $url, [
            'headers' => $this->mergeHeaders($headers),
            'json' => $json,
        ]);
    }

    /** @param array<array<string, mixed>> $multipart */
    public function postMultipart(string $url, array $multipart, array $headers = []): ResponseInterface
    {
        $merged = $this->mergeHeaders($headers);
        unset($merged['Content-Type']);

        return $this->request('POST', $url, [
            'headers' => $merged,
            'multipart' => $multipart,
        ]);
    }

    protected function request(string $method, string $url, array $options): ResponseInterface
    {
        try {
            $response = $this->client->request($method, $url, $options);

            $this->checkForErrors($response, $url);

            return $response;
        } catch (RateLimitException|AuthenticationException $e) {
            throw $e;
        } catch (ConnectException $e) {
            throw new MarkdownForAgentsException(
                message: "Connection failed: {$e->getMessage()}",
                source: $url,
                previous: $e,
            );
        } catch (RequestException $e) {
            throw new MarkdownForAgentsException(
                message: "HTTP request failed: {$e->getMessage()}",
                source: $url,
                previous: $e,
            );
        } catch (\Throwable $e) {
            if ($e instanceof MarkdownForAgentsException) {
                throw $e;
            }

            throw new MarkdownForAgentsException(
                message: "Unexpected error: {$e->getMessage()}",
                source: $url,
                previous: $e,
            );
        }
    }

    protected function checkForErrors(ResponseInterface $response, string $url): void
    {
        $status = $response->getStatusCode();

        if ($status === 401) {
            throw AuthenticationException::invalidToken('', $url);
        }

        if ($status === 429) {
            $retryAfter = $this->parseRetryAfter($response);

            throw RateLimitException::fromResponse('', $url, $retryAfter);
        }
    }

    /** @return array<string, string> */
    protected function mergeHeaders(array $headers): array
    {
        $defaults = [];

        if ($this->apiToken !== null) {
            $defaults['Authorization'] = "Bearer {$this->apiToken}";
        }

        return array_merge($defaults, $headers);
    }

    protected function retryDecider(): \Closure
    {
        return function (int $retries, ?Request $request, ?Response $response, ?\Throwable $exception): bool {
            if ($retries >= $this->maxRetries) {
                return false;
            }

            if ($exception instanceof ConnectException) {
                return true;
            }

            if ($response !== null) {
                $status = $response->getStatusCode();

                if ($status === 429) {
                    return true;
                }

                if ($status >= 500) {
                    return true;
                }
            }

            return false;
        };
    }

    protected function retryDelay(): \Closure
    {
        return function (int $retries, ?Response $response): int {
            if ($response !== null && $response->getStatusCode() === 429) {
                $retryAfter = $this->parseRetryAfter($response);
                if ($retryAfter !== null) {
                    return $retryAfter * 1000;
                }
            }

            return $this->retryDelay * (int) pow(2, $retries);
        };
    }

    protected function parseRetryAfter(ResponseInterface $response): ?int
    {
        $header = $response->getHeaderLine('Retry-After');

        if ($header === '') {
            return null;
        }

        return (int) $header;
    }
}
