<?php

namespace Osaris\Discogs\Auth;

use SensitiveParameter;

/**
 * Application consumer key and secret. Authenticates the application only:
 * fine for database reads and search, not for user-owned resources.
 */
final class ConsumerKey implements Credentials
{
    public function __construct(
        private readonly string $key,
        #[SensitiveParameter] private readonly string $secret,
    ) {}

    public function authorizationHeader(): string
    {
        return 'Discogs key='.$this->key.', secret='.$this->secret;
    }

    public function actsAsUser(): bool
    {
        return false;
    }
}
