<?php

namespace Osaris\Discogs\Auth;

interface Credentials
{
    /**
     * The value of the Authorization header for a request.
     */
    public function authorizationHeader(): string;

    /**
     * Whether these credentials act on behalf of a Discogs user.
     */
    public function actsAsUser(): bool;
}
