<?php

namespace Osaris\Discogs\Auth;

use SensitiveParameter;

/**
 * Personal access token ("Generate token" in Discogs developer settings).
 * Acts as the token's owner.
 */
final class PersonalToken implements Credentials
{
    public function __construct(#[SensitiveParameter] private readonly string $token) {}

    public function authorizationHeader(): string
    {
        return 'Discogs token='.$this->token;
    }

    public function actsAsUser(): bool
    {
        return true;
    }
}
