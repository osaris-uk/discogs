<?php

namespace Osaris\Discogs;

use Illuminate\Http\Client\Response;

/**
 * Rate limit state reported by the most recent response.
 */
final class RateLimit
{
    public function __construct(
        public readonly int $limit,
        public readonly int $used,
        public readonly int $remaining,
    ) {}

    public static function fromResponse(Response $response): ?self
    {
        $limit = $response->header('X-Discogs-Ratelimit');

        if ($limit === '') {
            return null;
        }

        return new self(
            (int) $limit,
            (int) $response->header('X-Discogs-Ratelimit-Used'),
            (int) $response->header('X-Discogs-Ratelimit-Remaining'),
        );
    }

    public function exhausted(): bool
    {
        return $this->remaining <= 0;
    }
}
