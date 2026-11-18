<?php

namespace Osaris\Discogs;

use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Osaris\Discogs\Auth\OAuth;
use Osaris\Discogs\Exceptions\ConnectionException;
use Osaris\Discogs\Exceptions\DiscogsException;
use SensitiveParameter;

/**
 * The three-legged OAuth 1.0a flow:
 *
 *   1. requestToken($callback)  - store oauth_token_secret, redirect to authorize_url
 *   2. Discogs redirects back with oauth_token and oauth_verifier
 *   3. accessToken(...)         - exchange for a permanent token and secret
 */
class OAuthFlow
{
    public const AUTHORIZE_URL = 'https://www.discogs.com/oauth/authorize';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected string $consumerKey,
        #[SensitiveParameter] protected string $consumerSecret,
        protected array $config,
        protected string $userAgent,
    ) {}

    /**
     * Step 1: obtain a request token.
     *
     * @return array{oauth_token: string, oauth_token_secret: string, oauth_callback_confirmed: bool, authorize_url: string}
     */
    public function requestToken(?string $callback = null): array
    {
        $response = $this->send('GET', 'oauth/request_token', OAuth::header(
            $this->consumerKey,
            $this->consumerSecret,
            extra: $callback !== null ? ['oauth_callback' => $callback] : [],
        ));

        $token = $this->parse($response);

        return [
            'oauth_token' => $token['oauth_token'],
            'oauth_token_secret' => $token['oauth_token_secret'],
            'oauth_callback_confirmed' => ($token['oauth_callback_confirmed'] ?? '') === 'true',
            'authorize_url' => $this->authorizeUrl($token['oauth_token']),
        ];
    }

    /**
     * Step 2: where to send the user to approve access.
     */
    public function authorizeUrl(string $requestToken): string
    {
        return self::AUTHORIZE_URL.'?oauth_token='.rawurlencode($requestToken);
    }

    /**
     * Step 3: exchange the request token and verifier for an access token.
     * The verifier expires 15 minutes after the user approves.
     *
     * @return array{oauth_token: string, oauth_token_secret: string}
     */
    public function accessToken(
        string $requestToken,
        #[SensitiveParameter] string $requestTokenSecret,
        string $verifier,
    ): array {
        $response = $this->send('POST', 'oauth/access_token', OAuth::header(
            $this->consumerKey,
            $this->consumerSecret,
            $requestToken,
            $requestTokenSecret,
            ['oauth_verifier' => $verifier],
        ));

        $token = $this->parse($response);

        return [
            'oauth_token' => $token['oauth_token'],
            'oauth_token_secret' => $token['oauth_token_secret'],
        ];
    }

    protected function send(string $method, string $uri, string $authorization): Response
    {
        $baseUrl = $this->config['base_url'] ?? null;

        try {
            $response = Http::baseUrl(rtrim(is_string($baseUrl) ? $baseUrl : 'https://api.discogs.com', '/'))
                ->withUserAgent($this->userAgent)
                ->withHeaders([
                    'Authorization' => $authorization,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->timeout($this->seconds('timeout', 30))
                ->connectTimeout($this->seconds('connect_timeout', 10))
                ->send($method, $uri);
        } catch (HttpConnectionException $e) {
            throw ConnectionException::from($e);
        }

        if ($response->failed()) {
            throw DiscogsException::fromResponse($response);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    protected function parse(Response $response): array
    {
        parse_str($response->body(), $parsed);

        $values = [];

        foreach ($parsed as $name => $value) {
            if (is_string($value)) {
                $values[(string) $name] = $value;
            }
        }

        if (! isset($values['oauth_token'], $values['oauth_token_secret'])) {
            $exception = new DiscogsException('Discogs returned an unexpected OAuth response.');
            $exception->response = $response;

            throw $exception;
        }

        return $values;
    }

    protected function seconds(string $key, int $default): int
    {
        $value = $this->config[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }
}
