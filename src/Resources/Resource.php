<?php

namespace Osaris\Discogs\Resources;

use Osaris\Discogs\Client;
use Osaris\Discogs\Discogs;

abstract class Resource
{
    protected Client $client;

    public function __construct(protected Discogs $discogs)
    {
        $this->client = $discogs->client();
    }

    /**
     * A URL-safe username segment, defaulting to the client's own user.
     */
    protected function user(?string $username): string
    {
        return rawurlencode($username ?? $this->discogs->username());
    }
}
