<?php

namespace Osaris\Discogs\Exceptions;

class MissingCredentialsException extends DiscogsException
{
    public static function make(): self
    {
        return new self(
            'No Discogs credentials are configured. This package only sends authenticated requests: '
            .'set DISCOGS_TOKEN, or DISCOGS_CONSUMER_KEY and DISCOGS_CONSUMER_SECRET, '
            .'or supply credentials with Discogs::withToken() / Discogs::withOAuth().'
        );
    }

    public static function consumer(): self
    {
        return new self(
            'The OAuth flow needs DISCOGS_CONSUMER_KEY and DISCOGS_CONSUMER_SECRET to be set.'
        );
    }
}
