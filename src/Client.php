<?php

namespace Osaris\Discogs;

use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Osaris\Discogs\Auth\Credentials;
use Osaris\Discogs\Exceptions\ConnectionException;
use Osaris\Discogs\Exceptions\DiscogsException;
use Osaris\Discogs\Exceptions\MissingCredentialsException;

/**
 * HTTP transport. Every request carries an Authorization header; without
 * credentials nothing is sent and MissingCredentialsException is thrown.
 */
class Client
{
    protected ?RateLimit $rateLimit = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected ?Credentials $credentials,
        protected array $config = [],
    ) {}

    public function credentials(): ?Credentials
    {
        return $this->credentials;
    }

    /**
     * Rate limit state from the most recent response, if any.
     */
    public function rateLimit(): ?RateLimit
    {
        return $this->rateLimit;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->decode($this->send('GET', $uri, $query));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    public function post(string $uri, array $data = [], array $query = []): array
    {
        return $this->decode($this->send('POST', $uri, $query, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public function put(string $uri, array $data = []): array
    {
        return $this->decode($this->send('PUT', $uri, [], $data));
    }

    public function delete(string $uri): bool
    {
        return $this->send('DELETE', $uri)->successful();
    }

    /**
     * Fetch one page of a paginated endpoint.
     *
     * @param  string  $key  The response key holding the page's items.
     * @param  array<string, mixed>  $query
     */
    public function paginate(string $uri, string $key, array $query = []): Page
    {
        return new Page($this, $uri, $key, $query, $this->get($uri, $query));
    }

    /**
     * POST a file as multipart/form-data.
     *
     * @return array<mixed>
     */
    public function upload(string $uri, string $field, string $contents, string $filename): array
    {
        return $this->decode($this->send(
            'POST', $uri,
            prepare: fn (PendingRequest $request) => $request->attach($field, $contents, $filename),
            options: ['multipart' => []],
        ));
    }

    /**
     * GET a non-JSON body (for example an inventory export CSV).
     */
    public function download(string $uri): string
    {
        return $this->send('GET', $uri)->body();
    }

    /**
     * Send a request, retrying as configured, and throw on any failure.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function send(
        string $method,
        string $uri,
        array $query = [],
        array $data = [],
        ?callable $prepare = null,
        array $options = [],
    ): Response {
        $credentials = $this->credentials ?? throw MissingCredentialsException::make();

        $query = $this->clean($query);
        $data = $this->clean($data, stringifyBooleans: false);

        if ($query !== []) {
            $options['query'] = $query;
        }

        if ($data !== []) {
            $options['json'] = $data;
        }

        $rateLimitRetries = max(0, (int) ($this->config['rate_limit']['retries'] ?? 0));
        $rateLimited = 0;

        // Only GETs are retried after a connection error or 5xx: a POST may
        // have taken effect (a listing created, an order invoiced) even
        // though no usable response came back.
        $failureRetries = strtoupper($method) === 'GET' ? max(0, (int) ($this->config['retry']['times'] ?? 0)) : 0;
        $failures = 0;

        while (true) {
            $this->throttle();

            $request = $this->request($credentials);

            if ($prepare) {
                $request = $prepare($request) ?? $request;
            }

            try {
                $response = $request->send($method, ltrim($uri, '/'), $options);
            } catch (HttpConnectionException $e) {
                if ($failures < $failureRetries) {
                    $this->backoff(++$failures);

                    continue;
                }

                throw ConnectionException::from($e);
            }

            $this->rateLimit = RateLimit::fromResponse($response) ?? $this->rateLimit;

            if ($response->status() === 429 && $rateLimited < $rateLimitRetries) {
                $rateLimited++;
                $this->rateLimit = null;

                Sleep::for($this->retryAfter($response))->seconds();

                continue;
            }

            if ($response->serverError() && $failures < $failureRetries) {
                $this->backoff(++$failures);

                continue;
            }

            if ($response->failed()) {
                throw DiscogsException::fromResponse($response);
            }

            return $response;
        }
    }

    protected function request(Credentials $credentials): PendingRequest
    {
        return Http::baseUrl(rtrim((string) ($this->config['base_url'] ?? 'https://api.discogs.com'), '/'))
            ->withUserAgent($this->userAgent())
            ->accept($this->acceptHeader())
            ->withHeaders(['Authorization' => $credentials->authorizationHeader()])
            ->timeout((int) ($this->config['timeout'] ?? 30))
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 10));
    }

    public function userAgent(): string
    {
        $agent = $this->config['user_agent'] ?? null;

        return is_string($agent) && trim($agent) !== '' ? trim($agent) : 'OsarisDiscogs/1.0';
    }

    protected function acceptHeader(): string
    {
        $format = $this->config['text_format'] ?? 'discogs';
        $format = in_array($format, ['discogs', 'plaintext', 'html'], true) ? $format : 'discogs';

        return 'application/vnd.discogs.v2.'.$format.'+json';
    }

    /**
     * Pause before a request when the last response reported no requests left.
     */
    protected function throttle(): void
    {
        if (! ($this->config['rate_limit']['throttle'] ?? false) || ! $this->rateLimit?->exhausted()) {
            return;
        }

        $this->rateLimit = null;

        Sleep::for($this->wait())->seconds();
    }

    protected function retryAfter(Response $response): int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) && (int) $header > 0 ? (int) $header : $this->wait();
    }

    protected function wait(): int
    {
        return max(1, (int) ($this->config['rate_limit']['wait'] ?? 60));
    }

    /**
     * Linear backoff before retrying a failed GET.
     */
    protected function backoff(int $attempt): void
    {
        Sleep::for(max(0, (int) ($this->config['retry']['sleep'] ?? 500)) * $attempt)->milliseconds();
    }

    /**
     * @return array<mixed>
     */
    protected function decode(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Drop nulls; in query strings, send booleans as "true"/"false".
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function clean(array $values, bool $stringifyBooleans = true): array
    {
        $values = array_filter($values, fn ($value) => $value !== null);

        if ($stringifyBooleans) {
            $values = array_map(fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : $value, $values);
        }

        return $values;
    }
}
