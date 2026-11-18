<?php

namespace Osaris\Discogs;

use Osaris\Discogs\Auth\ConsumerKey;
use Osaris\Discogs\Auth\Credentials;
use Osaris\Discogs\Auth\OAuth;
use Osaris\Discogs\Auth\PersonalToken;
use Osaris\Discogs\Exceptions\DiscogsException;
use Osaris\Discogs\Exceptions\MissingCredentialsException;
use Osaris\Discogs\Resources\Collection;
use Osaris\Discogs\Resources\Database;
use Osaris\Discogs\Resources\Inventory;
use Osaris\Discogs\Resources\Lists;
use Osaris\Discogs\Resources\Marketplace;
use Osaris\Discogs\Resources\Users;
use Osaris\Discogs\Resources\Wantlist;
use SensitiveParameter;

/**
 * @phpstan-consistent-constructor
 */
class Discogs
{
    protected Client $client;

    protected ?string $username;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(?Credentials $credentials, protected array $config = [])
    {
        $this->client = new Client($credentials, $config);
        $this->username = $this->configured('username');
    }

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    */

    /**
     * A new client using the given credentials. The default username is not
     * carried over, since the credentials may belong to someone else.
     */
    public function withCredentials(Credentials $credentials, ?string $username = null): static
    {
        return new static($credentials, ['username' => $username] + $this->config);
    }

    /**
     * A new client acting as the owner of a personal access token.
     */
    public function withToken(#[SensitiveParameter] string $token, ?string $username = null): static
    {
        return $this->withCredentials(new PersonalToken($token), $username);
    }

    /**
     * A new client acting as the user behind an OAuth access token, using
     * the configured consumer key and secret.
     */
    public function withOAuth(string $token, #[SensitiveParameter] string $tokenSecret, ?string $username = null): static
    {
        [$key, $secret] = $this->consumer();

        return $this->withCredentials(new OAuth($key, $secret, $token, $tokenSecret), $username);
    }

    /**
     * A new client authenticated as the application only (no user).
     */
    public function withConsumer(?string $key = null, #[SensitiveParameter] ?string $secret = null): static
    {
        if ($key === null || $secret === null) {
            [$key, $secret] = $this->consumer();
        }

        return $this->withCredentials(new ConsumerKey($key, $secret));
    }

    /**
     * The OAuth 1.0a authorisation flow, for connecting other users' accounts.
     */
    public function oauth(): OAuthFlow
    {
        [$key, $secret] = $this->consumer();

        return new OAuthFlow($key, $secret, $this->config, $this->client->userAgent());
    }

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */

    public function database(): Database
    {
        return new Database($this);
    }

    public function marketplace(): Marketplace
    {
        return new Marketplace($this);
    }

    public function inventory(): Inventory
    {
        return new Inventory($this);
    }

    public function users(): Users
    {
        return new Users($this);
    }

    public function collection(): Collection
    {
        return new Collection($this);
    }

    public function wantlist(): Wantlist
    {
        return new Wantlist($this);
    }

    public function lists(): Lists
    {
        return new Lists($this);
    }

    /*
    |--------------------------------------------------------------------------
    | Shortcuts and state
    |--------------------------------------------------------------------------
    */

    /**
     * The authenticated user's identity (id, username, resource_url, consumer_name).
     *
     * @return array<mixed>
     */
    public function identity(): array
    {
        return $this->users()->identity();
    }

    /**
     * The username used when a user-scoped call is not given one: the
     * configured username, else the authenticated user's (looked up once).
     *
     * @throws DiscogsException when neither is available.
     */
    public function username(): string
    {
        if ($this->username !== null) {
            return $this->username;
        }

        $username = $this->identity()['username'] ?? null;

        if (! is_string($username) || trim($username) === '') {
            throw new DiscogsException(
                'Could not determine the Discogs username. Pass one to the method, set DISCOGS_USERNAME, '
                .'or use credentials that act as a user.'
            );
        }

        return $this->username = trim($username);
    }

    public function client(): Client
    {
        return $this->client;
    }

    /**
     * Rate limit state from the most recent response, if any.
     */
    public function rateLimit(): ?RateLimit
    {
        return $this->client->rateLimit();
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function consumer(): array
    {
        $key = $this->configured('consumer_key');
        $secret = $this->configured('consumer_secret');

        if ($key === null || $secret === null) {
            throw MissingCredentialsException::consumer();
        }

        return [$key, $secret];
    }

    protected function configured(string $key): ?string
    {
        $value = $this->config[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
