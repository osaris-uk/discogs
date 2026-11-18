<?php

namespace Osaris\Discogs\Exceptions;

use Throwable;

/**
 * Discogs could not be reached: a timeout, DNS failure or refused connection.
 * No response is available.
 */
class ConnectionException extends DiscogsException
{
    public static function from(Throwable $previous): self
    {
        return new self('Could not connect to Discogs: '.$previous->getMessage(), 0, $previous);
    }
}
